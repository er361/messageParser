<?php
/**
 * Created by PhpStorm.
 * User: cs-user2017
 * Date: 1/30/2018
 * Time: 1:35 PM
 */

namespace MColl\helpers;


use Ddeboer\Imap\Message\Attachment;
use DOMDocument;

class AttachmentsHelper
{
    protected static function allowToSave($type)
    {
        if(
            strcasecmp($type,'png') == 0 or
            strcasecmp($type, 'pdf') == 0 or
            strcasecmp($type,'jpg') == 0 or
            strcasecmp($type,'jpeg') == 0
        )
            return true;
        return false;
    }


    public static function downloadAttachments(Attachment $attachment)
    {
        /* @var $attachment Attachment */

        if (static::allowToSave($attachment->getSubtype())) {
            $hashFileName = static::getHashFileName($attachment);
            $dirPath = __DIR__ . '/../attachments/';
            if(!file_exists($dirPath))
                mkdir($dirPath);
            $path = __DIR__ . '/../attachments/' . $hashFileName;
            return file_put_contents($path, $attachment->getDecodedContent());
        }
        return false;
    }

//    public static function getImgCid($html)
//    {
//        $DOMXPath = new \DOMXPath(static::getDom($html));
////        $clearId = trim($cid, '<>');
//        $query = '//img[contains(@src,"' . $clearId . '")]';
//        $DOMNodeList = $DOMXPath->query($query);
//        if ($DOMNodeList instanceof \DOMNodeList and $DOMNodeList->length > 0) {
//            $DOMElement = $DOMNodeList->item(0);
//            $DOMElement->setAttribute('src',$dbFileName);
//            d($DOMElement->getAttribute('src'));
//        }
//        $saveHTML = $DOMXPath->document->saveHTML($DOMXPath->document->documentElement);
//        return $saveHTML;
//    }

    private static function getDom($html)
    {
        $source = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML($source, LIBXML_NOWARNING);
        return $dom;
    }


    /**
     * @param Attachment $attachment
     * @return array
     */
    public static function getHashFileName(Attachment $attachment)
    {
        $hash = md5($attachment->getFilename());
        $ext = $attachment->getSubtype();
        return $hash . '.' . $ext;
    }
}