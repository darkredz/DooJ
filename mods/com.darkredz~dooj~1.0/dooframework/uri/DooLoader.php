<?php
/**
 * DooLoader class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2013 Leng Sheng Hong
 * @license http://www.doophp.com/license-v2
 */

/**
 * A class that provides shorthand functions to access/load files on the server.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooController.php 1000 2009-07-7 18:27:22
 * @package doo.uri
 * @since 1.0
 */
class DooLoader
{

    public $app;

    /**
     * Reads a file and send a header to force download it.
     * @param string $file_str File name with absolute path to it
     * @param bool $isLarge If True, the large file will be read chunk by chunk into the memory.
     * @param string $rename Name to replace the file name that would be downloaded
     */
    public function download($file, $isLarge = false, $rename = null)
    {
        if ($rename == null) {
            if (strpos($file, '/') === false && strpos($file, '\\') === false) {
                $filename = $file;
            } else {
                $filename = basename($file);
            }
        } else {
            $filename = $rename;
        }

        $this->app->setHeader('Content-Description: File Transfer');
        $this->app->setHeader('Content-Type: application/octet-stream');
        $this->app->setHeader("Content-Disposition: attachment; filename=\"$filename\"");
        $this->app->setHeader('Expires: 0');
        $this->app->setHeader('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        $this->app->setHeader('Pragma: public');
        $this->app->setHeader('Content-Length: ' . filesize($file));
        ob_clean();
        flush();

        if ($isLarge) {
            $this->readfile_chunked($file);
        } else {
            readfile($file);
        }
    }

    /**
     * Read a file and display its content chunk by chunk
     * @param string $filename
     * @param bool $retbytes
     * @return mixed
     */
    private function readfile_chunked($filename, $retbytes = true, $chunk_size = 1024)
    {
        $buffer = '';
        $cnt = 0;
        // $handle = fopen($filename, 'rb');
        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            return false;
        }
        while (!feof($handle)) {
            $buffer = fread($handle, $chunk_size);
            echo $buffer;
            ob_flush();
            flush();
            if ($retbytes) {
                $cnt += strlen($buffer);
            }
        }
        $status = fclose($handle);
        if ($retbytes && $status) {
            return $cnt; // return num. bytes delivered like readfile() does.
        }
        return $status;
    }
}
