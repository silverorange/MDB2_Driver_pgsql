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
 * MDB2 PostGreSQL buffered result driver
 *
 * @category Database
 * @package  MDB2
 * @author   Paul Cooper <pgc@ucecom.com>
 * @license  http://opensource.org/licenses/bsd-license.php BSD-2-Clause
 */
class MDB2_BufferedResult_pgsql extends MDB2_Result_pgsql
{
    // {{{ seek()

    /**
     * Seek to a specific row in a result set
     *
     * @param int    $rownum    number of the row where the data can be found
     * @return mixed MDB2_OK on success, a MDB2 error on failure
     */
    public function seek($rownum = 0)
    {
        if ($this->rownum != ($rownum - 1) && !@pg_result_seek($this->result, $rownum)) {
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
                return MDB2_OK;
            }
            return $this->db->raiseError(
                MDB2_ERROR_INVALID,
                null,
                null,
                'tried to seek to an invalid row number (' . $rownum . ')',
                __FUNCTION__
            );
        }
        $this->rownum = $rownum - 1;
        return MDB2_OK;
    }

    // }}}
    // {{{ valid()

    /**
     * Check if the end of the result set has been reached
     *
     * @return mixed true or false on sucess, a MDB2 error on failure
     */
    public function valid()
    {
        $numrows = $this->numRows();
        if (MDB2::isError($numrows)) {
            return $numrows;
        }
        return $this->rownum < ($numrows - 1);
    }

    // }}}
    // {{{ numRows()

    /**
     * Returns the number of rows in a result object
     *
     * @return mixed MDB2 Error Object or the number of rows
     */
    public function numRows()
    {
        $rows = @pg_num_rows($this->result);
        if (null === $rows) {
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
                return 0;
            }
            return $this->db->raiseError(
                null,
                null,
                null,
                'Could not get row count',
                __FUNCTION__
            );
        }
        return $rows;
    }

    // }}}
}

?>
