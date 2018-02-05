<?php
/**
 * Created by PhpStorm.
 * User: cs-user2017
 * Date: 1/30/2018
 * Time: 1:45 PM
 */

namespace MColl\traits;


trait InlineImagesTrait
{
    public function replaceImagePaths($html)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
    }
}