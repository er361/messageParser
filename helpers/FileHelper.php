<?php
/**
 * Created by PhpStorm.
 * User: cs-user2017
 * Date: 1/18/2018
 * Time: 1:13 PM
 */
namespace MColl\helpers;

use Ddeboer\Imap\Message\Attachment;
use LessQL\Database;

class FileHelper
{
    public static function writeFile($file_name,$file_data){
        $messages_file = $file_name;
        $handle = fopen($messages_file, 'w');
        $data = json_encode($file_data);
        fwrite($handle,$data);
    }

    public static function readFile($file_name){
        $handle = fopen($file_name,'r');
        $data = fread($handle,filesize($file_name));
        return $data;
    }
}