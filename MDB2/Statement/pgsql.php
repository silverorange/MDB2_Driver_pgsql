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
 * MDB2 PostGreSQL statement driver
 *
 * @category Database
 * @package  MDB2
 * @author   Paul Cooper <pgc@ucecom.com>
 * @license  http://opensource.org/licenses/bsd-license.php BSD-2-Clause
 */
class MDB2_Statement_pgsql extends MDB2_Statement_Common
{
    // {{{ executeInternal()

    /**
     * Execute a prepared query statement helper method.
     *
     * @param mixed $result_class string which specifies which result class to use
     * @param mixed $result_wrap_class string which specifies which class to wrap results in
     *
     * @return mixed MDB2_Result or integer (affected rows) on success,
     *               a MDB2 error on failure
     */
    protected function executeInternal($result_class = true, $result_wrap_class = true)
    {
        if (null === $this->statement) {
            return parent::executeInternal($result_class, $result_wrap_class);
        }
        $this->db->last_query = $this->query;
        $this->db->debug($this->query, 'execute', array('is_manip' => $this->is_manip, 'when' => 'pre', 'parameters' => $this->values));
        if ($this->db->getOption('disable_query')) {
            $result = $this->is_manip ? 0 : null;
            return $result;
        }

        $connection = $this->db->getConnection();
        if (MDB2::isError($connection)) {
            return $connection;
        }

        $query = false;
        $parameters = array();
        // todo: disabled until pgexecuteInternal() bytea issues are cleared up
        if (true || !function_exists('pgexecuteInternal')) {
            $query = 'EXECUTE '.$this->statement;
        }
        if (!empty($this->positions)) {
            foreach ($this->positions as $parameter) {
                if (!array_key_exists($parameter, $this->values)) {
                    return $this->db->raiseError(
                        MDB2_ERROR_NOT_FOUND,
                        null,
                        null,
                        'Unable to bind to missing placeholder: ' . $parameter,
                        __FUNCTION__
                    );
                }
                $value = $this->values[$parameter];
                $type = array_key_exists($parameter, $this->types) ? $this->types[$parameter] : null;
                if (is_resource($value) || $type == 'clob' || $type == 'blob' || $this->db->options['lob_allow_url_include']) {
                    if (!is_resource($value) && preg_match('/^(\w+:\/\/)(.*)$/', $value, $match)) {
                        if ($match[1] == 'file://') {
                            $value = $match[2];
                        }
                        $value = @fopen($value, 'r');
                        $close = true;
                    }
                    if (is_resource($value)) {
                        $data = '';
                        while (!@feof($value)) {
                            $data.= @fread($value, $this->db->options['lob_buffer_length']);
                        }
                        if ($close) {
                            @fclose($value);
                        }
                        $value = $data;
                    }
                }
                $quoted = $this->db->quote($value, $type, $query);
                if (MDB2::isError($quoted)) {
                    return $quoted;
                }
                $parameters[] = $quoted;
            }
            if ($query) {
                $query.= ' ('.implode(', ', $parameters).')';
            }
        }

        if (!$query) {
            $result = @pgexecuteInternal($connection, $this->statement, $parameters);
            if (!$result) {
                $err = $this->db->raiseError(
                    null,
                    null,
                    null,
                    'Unable to execute statement',
                    __FUNCTION__
                );
                return $err;
            }
        } else {
            $result = $this->db->doQuery($query, $this->is_manip, $connection);
            if (MDB2::isError($result)) {
                return $result;
            }
        }

        if ($this->is_manip) {
            $affected_rows = $this->db->affectedRows($connection, $result);
            return $affected_rows;
        }

        $result = $this->db->wrapResult(
            $result,
            $this->result_types,
            $result_class,
            $result_wrap_class,
            $this->limit,
            $this->offset
        );

        $this->db->debug($this->query, 'execute', array('is_manip' => $this->is_manip, 'when' => 'post', 'result' => $result));
        return $result;
    }

    // }}}
    // {{{ free()

    /**
     * Release resources allocated for the specified prepared query.
     *
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    public function free()
    {
        if (null === $this->positions) {
            return $this->db->raiseError(
                MDB2_ERROR,
                null,
                null,
                'Prepared statement has already been freed',
                __FUNCTION__
            );
        }
        $result = MDB2_OK;

        if (null !== $this->statement) {
            $connection = $this->db->getConnection();
            if (MDB2::isError($connection)) {
                return $connection;
            }
            $query = 'DEALLOCATE PREPARE '.$this->statement;
            $result = $this->db->doQuery($query, true, $connection);
        }

        parent::free();
        return $result;
    }

    // }}}
    // {{{ dropTable()

    /**
     * drop an existing table
     *
     * @param string $name name of the table that should be dropped
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    public function dropTable($name)
    {
        $db = $this->getDBInstance();
        if (MDB2::isError($db)) {
            return $db;
        }

        $name = $db->quoteIdentifier($name, true);
        $result = $db->exec("DROP TABLE $name");

        if (MDB2::isError($result)) {
            $result = $db->exec("DROP TABLE $name CASCADE");
        }

        return $result;
    }

    // }}}
}

?>
