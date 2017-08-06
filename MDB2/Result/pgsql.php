<?php

/**
 * +----------------------------------------------------------------------+
 * | PHP version 5                                                        |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1998-2008 Manuel Lemos, Tomas V.V.Cox,                 |
 * | Stig. S. Bakken, Lukas Smith                                         |
 * | All rights reserved.                                                 |
 * +----------------------------------------------------------------------+
 * | MDB2 is a merge of PEAR DB and Metabases that provides a unified DB  |
 * | API as well as database abstraction for PHP applications.            |
 * | This LICENSE is in the BSD license style.                            |
 * |                                                                      |
 * | Redistribution and use in source and binary forms, with or without   |
 * | modification, are permitted provided that the following conditions   |
 * | are met:                                                             |
 * |                                                                      |
 * | Redistributions of source code must retain the above copyright       |
 * | notice, this list of conditions and the following disclaimer.        |
 * |                                                                      |
 * | Redistributions in binary form must reproduce the above copyright    |
 * | notice, this list of conditions and the following disclaimer in the  |
 * | documentation and/or other materials provided with the distribution. |
 * |                                                                      |
 * | Neither the name of Manuel Lemos, Tomas V.V.Cox, Stig. S. Bakken,    |
 * | Lukas Smith nor the names of his contributors may be used to endorse |
 * | or promote products derived from this software without specific prior|
 * | written permission.                                                  |
 * |                                                                      |
 * | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS  |
 * | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT    |
 * | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS    |
 * | FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE      |
 * | REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,          |
 * | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, |
 * | BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS|
 * |  OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED  |
 * | AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT          |
 * | LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY|
 * | WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE          |
 * | POSSIBILITY OF SUCH DAMAGE.                                          |
 * +----------------------------------------------------------------------+
 * | Author: Paul Cooper <pgc@ucecom.com>                                 |
 * +----------------------------------------------------------------------+
 */

/**
 * MDB2 PostGreSQL result driver
 *
 * @category Database
 * @package  MDB2
 * @author   Paul Cooper <pgc@ucecom.com>
 * @license  http://opensource.org/licenses/bsd-license.php BSD-2-Clause
 */
// @codingStandardsIgnoreLine
class MDB2_Result_pgsql extends MDB2_Result_Common
{
    // {{{ fetchRow()

    /**
     * Fetch a row and insert the data into an existing array.
     *
     * @param int       $fetchmode  how the array data should be indexed
     * @param int    $rownum    number of the row where the data can be found
     * @return int data array on success, a MDB2 error on failure
     */
    public function fetchRow($fetchmode = MDB2_FETCHMODE_DEFAULT, $rownum = null)
    {
        if (null !== $rownum) {
            $seek = $this->seek($rownum);
            if (MDB2::isError($seek)) {
                return $seek;
            }
        }
        if ($fetchmode == MDB2_FETCHMODE_DEFAULT) {
            $fetchmode = $this->db->fetchmode;
        }
        if ($fetchmode == MDB2_FETCHMODE_ASSOC
            || $fetchmode == MDB2_FETCHMODE_OBJECT
        ) {
            $row = @pg_fetch_array($this->result, null, PGSQL_ASSOC);
            if (is_array($row)
                && $this->db->options['portability'] & MDB2_PORTABILITY_FIX_CASE
            ) {
                $row = array_change_key_case($row, $this->db->options['field_case']);
            }
        } else {
            $row = @pg_fetch_row($this->result);
        }
        if (!$row) {
            if (false === $this->result) {
                $err = $this->db->raiseError(
                    MDB2_ERROR_NEED_MORE_DATA,
                    null,
                    null,
                    'resultset has already been freed',
                    __FUNCTION__
                );
                return $err;
            }
            return null;
        }
        $mode = $this->db->options['portability'] & MDB2_PORTABILITY_EMPTY_TO_NULL;
        $rtrim = false;
        if ($this->db->options['portability'] & MDB2_PORTABILITY_RTRIM) {
            if (empty($this->types)) {
                $mode += MDB2_PORTABILITY_RTRIM;
            } else {
                $rtrim = true;
            }
        }
        if ($mode) {
            $this->db->fixResultArrayValues($row, $mode);
        }
        if (!empty($this->types)) {
            $row = $this->db->datatype->convertResultRow($this->types, $row, $rtrim);
        } elseif (($fetchmode == MDB2_FETCHMODE_ASSOC
            || $fetchmode == MDB2_FETCHMODE_OBJECT)
            && !empty($this->types_assoc)
        ) {
            $row = $this->db->datatype->convertResultRow($this->types_assoc, $row, $rtrim);
        }
        if (!empty($this->values)) {
            $this->assignBindColumns($row);
        }
        if ($fetchmode === MDB2_FETCHMODE_OBJECT) {
            $object_class = $this->db->options['fetch_class'];
            if ($object_class == 'stdClass') {
                $row = (object) $row;
            } else {
                $rowObj = new $object_class($row);
                $row = $rowObj;
            }
        }
        ++$this->rownum;
        return $row;
    }

    // }}}
    // {{{ getColumnNames()

    /**
     * Retrieve the names of columns returned by the DBMS in a query result.
     *
     * @return  mixed   Array variable that holds the names of columns as keys
     *                  or an MDB2 error on failure.
     *                  Some DBMS may not return any columns when the result set
     *                  does not contain any rows.
     */
    public function getColumnNames($flip = false)
    {
        $columns = array();
        $numcols = $this->numCols();
        if (MDB2::isError($numcols)) {
            return $numcols;
        }
        for ($column = 0; $column < $numcols; $column++) {
            $column_name = @pg_field_name($this->result, $column);
            $columns[$column_name] = $column;
        }
        if ($this->db->options['portability'] & MDB2_PORTABILITY_FIX_CASE) {
            $columns = array_change_key_case($columns, $this->db->options['field_case']);
        }
        return $columns;
    }

    // }}}
    // {{{ numCols()

    /**
     * Count the number of columns returned by the DBMS in a query result.
     *
     * @return mixed integer value with the number of columns, a MDB2 error
     *                       on failure
     */
    public function numCols()
    {
        $cols = @pg_num_fields($this->result);
        if (null === $cols) {
            if (false === $this->result) {
                return $this->db->raiseError(
                    MDB2_ERROR_NEED_MORE_DATA,
                    null,
                    null,
                    'resultset has already been freed',
                    __FUNCTION__
                );
            }
            if (null === $this->result) {
                return count($this->types);
            }
            return $this->db->raiseError(
                null,
                null,
                null,
                'Could not get column count',
                __FUNCTION__
            );
        }
        return $cols;
    }

    // }}}
    // {{{ nextResult()

    /**
     * Move the internal result pointer to the next available result
     *
     * @return true on success, false if there is no more result set or an error object on failure
     */
    public function nextResult()
    {
        $connection = $this->db->getConnection();
        if (MDB2::isError($connection)) {
            return $connection;
        }

        if (!($this->result = @pg_get_result($connection))) {
            return false;
        }
        return MDB2_OK;
    }

    // }}}
    // {{{ free()

    /**
     * Free the internal resources associated with result.
     *
     * @return boolean true on success, false if result is invalid
     */
    public function free()
    {
        if (is_resource($this->result) && $this->db->connection) {
            $free = @pg_free_result($this->result);
            if (false === $free) {
                return $this->db->raiseError(
                    null,
                    null,
                    null,
                    'Could not free result',
                    __FUNCTION__
                );
            }
        }
        $this->result = false;
        return MDB2_OK;
    }

    // }}}
}

?>
