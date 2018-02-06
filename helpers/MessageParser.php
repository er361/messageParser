<?php
/**
 * Created by PhpStorm.
 * User: cs-user2017
 * Date: 1/19/2018
 * Time: 9:02 AM
 */

namespace MColl\helpers;


use Ddeboer\Imap\Message;
use DOMDocument;
use DOMXPath;
use MColl\traits\InlineImagesTrait;

/* @var $message Message */
class MessageParser
{
    use InlineImagesTrait;
    protected $dom;
    protected $headers;
    protected $message;

    protected $bodyHtml;
    protected $bodyText;
    protected $bodyContent;

    public function __construct(Message $message)
    {
        /* @var $message Message */
        libxml_use_internal_errors(true);

        if ($message == null)
            throw new \Exception('message is null');

        $this->message = $message;
        $this->headers = $message->getHeaders();
        $this->setBody($message);


        /* @var $message Message */
        $content = $this->getContent($message);
        $source = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->loadHTML($source, LIBXML_NOWARNING);
    }

    protected function clearMailRu()
    {
        $nodeList = $this->dom
            ->getElementsByTagName('blockquote');
        foreach ($nodeList as $item) {
            /* @var $item \DOMNode */
            $textContent = $item->firstChild->textContent;
            if (preg_match("/([\w\.\-_]+)?\w+@[\w-_]+(\.\w+){1,}/",
                $textContent)) {
                if ($item->parentNode) {
                    $item->parentNode->removeChild($item);
                }
            }
        }
    }

    protected function clearGmail()
    {
        $domXpath = new DOMXPath($this->dom);
        $query = '//div[@class="gmail_extra"]|//div[@class="gmail_quote"]';
        $DOMNodeList = $domXpath->query($query);
        if ($DOMNodeList instanceof \DOMNodeList and $DOMNodeList->length > 0) {
            $DOMElement = $DOMNodeList->item(0);

            if ($DOMElement) {
                $DOMElement->parentNode->removeChild($DOMElement);
            }
        }
    }

    protected function clearYahoo()
    {
        $nodeList = $this->dom->getElementsByTagName('div');
        foreach ($nodeList as $item) {
            /* @var $item \DOMNode */
            $className = $item->attributes->getNamedItem('class')->nodeValue;
            if (preg_match('/yahoo_quoted/', $className)) {
                if ($item->parentNode) {
                    $item->parentNode->removeChild($item);
                }
            }
        }
    }

    protected function clearRambler()
    {
        $DOMNodeList = $this->dom->getElementsByTagName('blockquote');
        foreach ($DOMNodeList as $item) {
            /* @var $item \DOMNode */
            $textContent = $item->textContent;
            if (preg_match("/([\w\.\-_]+)?\w+@[\w-_]+(\.\w+){1,}/",
                $textContent)) {
                if ($item->parentNode) {
                    $item->parentNode->removeChild($item);
                }
            }
        }
    }

    protected function clearYandex()
    {
        $DOMXPath = new DOMXPath($this->dom);
        $DOMElement = $DOMXPath->query('//blockquote[@type="cite"]')->item(0);
        if ($DOMElement) {
            $DOMElement->parentNode->removeChild($DOMElement->previousSibling);
            $DOMElement->parentNode->removeChild($DOMElement);
        }

    }

    protected function clearOutlook()
    {
        $DOMXPath = new DOMXPath($this->dom);
        $query = '//div[contains(@style,"border:none;border-top:solid")]/../
        following-sibling::*';
        $DOMNodeList = $DOMXPath->query($query);
        foreach ($DOMNodeList as $item) {
            /* @var $item \DOMNode */
            if ($item->parentNode) {
                $item->parentNode->removeChild($item);
            }
        }
        $DOMElement = $DOMXPath->query('//div[contains(@style,"border:none;border-top:solid")]/..')
            ->item(0);
        if ($DOMElement->parentNode)
            $DOMElement->parentNode->removeChild($DOMElement);
    }

    protected function getSenderHost()
    {
        /* @var $headers Message\Headers */
        $senderArr = $this->headers->get('sender');
        if (is_array($senderArr)) {
            $sender = reset($senderArr);
            if (!$sender)
                throw new \Exception('there is no sender');
            return $sender->host;
        }
    }


    protected function getRefFromSubject()
    {
        /* @var $message Message */
        $message = $this->message;
        if ($message->getHeaders())
            $subject = $message->getSubject();
        if (preg_match('/ref #:[0-9]+/', $subject)) {
            $id = $this->extractId($subject);
            if ($id) {
                return $id;
            } else
                return false;
        }
    }

    protected function getRefFromBody()
    {
        $DOMXPath = new DOMXPath($this->dom);
        $DOMNodeList = $DOMXPath->query('//text()');
        foreach ($DOMNodeList as $item) {
            if (preg_match('/ref #:[0-9]+/', $item->nodeValue)) {
                $extractId = $this->extractId($item->nodeValue);
                if ($extractId) {
                    return $extractId;
                } else {
                    return false;
                }
            }
        }
    }

    private function extractId($str)
    {
        $explode = explode('#', $str);
        $id = substr($explode[1], 1);
        return $id;
    }

    public function getID()
    {
        if ($this->getRefFromSubject()) {
            return $this->getRefFromSubject();
        } else {
            return $this->getRefFromBody();
        }
        return false;
    }

    protected function isNeededToClear()
    {
        $params = $this->message->getHeaders()->parameters;
        if (key_exists('in_reply_to', $params))
            return true;
        return false;
    }

    /**
     * @return mixed|string
     * @throws \Exception
     */
    public function clearMessage()
    {
        $html = null;
        if ($this->isNeededToClear()) {
            $host = $this->getSenderHost();
            switch ($host) {
                case 'mail.ru':
                    $this->clearMailRu();
                    break;
                case 'gmail.com':
                    $this->clearGmail();
                    break;
                case 'yahoo.com':
                    $this->clearYahoo();
                    break;
                case 'rambler.ru':
                    $this->clearRambler();
                    break;
                case 'yandex.ru':
                    $this->clearYandex();
                    break;
                case 'yandex.kz':
                    $this->clearYandex();
                    break;
                default:
                    $this->clearOutlook();
                    $this->clearYandex();
                    $this->clearRambler();
                    $this->clearGmail();
                    $this->clearMailRu();
                    $this->clearYandex();
                    break;
            }
            return $this->dom->saveHTML($this->dom->documentElement);
        }

        return $this->getBody();
    }

    /**
     * @return mixed
     */
    protected function getBody()
    {
        if ($this->bodyContent)
            return $this->bodyContent;
        elseif ($this->bodyText)
            return $this->bodyText;
        elseif ($this->bodyHtml)
            return $this->bodyHtml;
    }

    /**
     * @param $message
     * @return mixed
     */
    protected function getContent($message)
    {
        /* @var $message Message */
        try {
            $content = $message->getBodyHtml();
            if (!$content)
                $content = $message->getBodyText();
        } catch (\UnexpectedValueException $exception) {
            $content = $message->getContent();
        }
        return $content;
    }

    /**
     * @param $message
     */
    protected function setBody($message)
    {
        /* @var $message Message */
        try {
            $bodyHtml = $message->getBodyHtml();
            if ($bodyHtml) {
                $this->bodyHtml = $bodyHtml;
            } elseif ($message->getBodyText())
                $this->bodyText = $message->getBodyText();
        } catch (\UnexpectedValueException $exception) {
            $this->bodyContent = $message->getContent();
        }
    }

}