<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
require __DIR__ . "/vendor/autoload.php";

use Ddeboer\Imap\Message;
use Ddeboer\Imap\Message\Attachment;
use Ddeboer\Imap\Search\Date\After;
use Ddeboer\Imap\Search\Text\Subject;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Server;
use Kint;
use MColl\helpers\AttachmentsHelper;
use MColl\helpers\MessageParser;
use MColl\tests\fixtures\FixturesCreate;


defined('_JEXEC') or die;
// Подключаем библиотеку контроллера Joomla.
jimport('joomla.application.component.controller');


/**
 * @property  html
 */
class CRMControllerCronEmailCollector extends JControllerLegacy
{

    private $html;

    protected $userSettings;
    protected $flags;

    public function __construct($config = array())
    {
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('display_errors', 1);

        parent::__construct($config);

        $this->userSettings = LibCron::getUsersSettings();

        $this->flags = "/imap/ssl/novalidate-cert";
//        d($this->userSettings);
//        exit();
    }

    protected function getLastMessage($messageIterator)
    {
        //get last message
        $messageIterator->seek($messageIterator->count() - 1);
        $last = $messageIterator->current();
        return $last;
    }

    /*
     * array_key_exists("profile_id", $fields) &&
                   array_key_exists("recepient_email_id", $fields) &&
                   array_key_exists("message_subject", $fields) &&
                   array_key_exists("message_content", $fields) &&
                   array_key_exists("message_content_plain", $fields) &&
                   array_key_exists("message_date", $fields) &&
                   array_key_exists("utc_timezone", $fields) &&
                   array_key_exists("was_read", $fields) &&
                   array_key_exists("owner_id", $fields) &&
                   array_key_exists("message_direction", $fields)
     */
    public function getNewMessages()
    {
        $inArray = [];
        $i = 0;
        foreach ($this->userSettings as $userSetting) {
            $i++;
//            if ($i >= 2)
//                break;
            $conf = $userSetting->settings->email_conf;
//            d($userSetting);
            if (!$conf->last_message_date) {
                $this->setLastMessageDate();
                continue;
            }

            //set datetime
            $dateTime = new DateTime();
            $dateTime->setTimestamp($conf->last_message_date);

            //search
            $search = new SearchExpression();
            $search->addCondition(new After($dateTime));
//            $search->addCondition(new Subject('Проверка Emails Collector'));
//            $search->addCondition(new Subject('Lola Tech NDA v002 (1).pdf'));

            //get messages
            $mailbox = $this->getMailBox($conf, 'INBOX');
            $messageIterator = $mailbox->getMessages($search);
            d($messageIterator->count());
            $i = 0;
            foreach ($messageIterator as $message) {
                $i++;
                if ($i == 10)
                    break;


                $message->keepUnseen(true);
                $messageParser = new MessageParser($message);
//                +d($message->getHeaders());
                //set out
                $params = [
                    'owner_id' => $userSetting->owner_id,
                    'ref_id' => $messageParser->getID(),
                    'recipients' => $this->getTo($message),
                    'sender_email' => $message->getFrom()->getAddress(),
                    'user_id' => $userSetting->user_id,
                    'user_email' => $userSetting->settings->email_conf->email,
                ];

                $profiles = $this->getProfiles($params);
//                if ($profiles) {
//                    d($params);
//                    d($message->getParts());
//                    d($profiles);
//                    die('profiles');
//                }
//                d($message->getHeaders());
//                echo $this->getBodyHtml($message);
//                d($message->getAttachments());
//                d($message->getParts());
//                d($message->getCc());
//                d($message->getChildren());
//                echo d($message->getParts());
//                continue;
//                $this->getMessageContent($message, $messageParser,);

                $owner_id = (int)$userSetting->owner_id;
                $messageId = $message->getId();

                if ($profiles) {
                    /*
                     * Сохранять в html класса в дальнейшем
                     * html модифицируеться другими методами
                     * не явно с этой части кода, в дальнейшем переделать
                     * чтобы модифицировался явно
                    */

                    $this->html = $messageParser->clearMessage();

                    $inArray['profiles'] = $profiles['data']['profiles'];
                    $inArray['owner_id'] = $owner_id;
                    $inArray['message_subject'] = $message->getSubject();
                    $inArray['message_content'] = $this->html;
                    $inArray['message_content_full'] = $this->getBodyHtml($message);
                    $inArray['message_date'] = $message->getDate()->getTimestamp();
                    $inArray['utc_timezone'] = $message->getHeaders()->get('date')->format('P');
                    $inArray['was_read'] = $message->isSeen();
                    $inArray['parsing_message_id'] = $message->getId();
                    $inArray['message_direction'] = $profiles['data']['message_direction'];
                    $inArray['messageObj'] = $message;
                    $out[] = $inArray;
                }
            }

            if (!empty($out)) {
                $outExt[] = $out;
            }
        }

        if (!empty($outExt)) {
            return $outExt;
        }
    }

    public function setLastMessageDate()
    {
        $out = [];
        $i = 0;
        foreach ($this->userSettings as $usersSetting) {
            $i++;
            $conf = $usersSetting->settings->email_conf;

            $mailbox = $this->getMailBox($conf);

            //setup date time
            $dateTime = new DateTime();
            $dateTime->sub(new DateInterval('P1D'));

            //search
            $searchExpression = new SearchExpression();
            $searchExpression->addCondition(new After($dateTime));

            //get messages
            $messageIterator = $mailbox->getMessages($searchExpression);

            //get last message
            if ($messageIterator->count() > 0)
                $last = $this->getLastMessage($messageIterator);
            else
                $last = null;

            //setLastMessageDate
            if ($conf->last_messsage_date)
                $lastMessageDate = $last->getDate()->getTimestamp();
            else
                $lastMessageDate = $dateTime->getTimestamp();


            //set last message date
            $params = [
                'user_id' => $usersSetting->user_id,
                'profile_id' => $usersSetting->profile_id,
                'owner_id' => $usersSetting->owner_id,
                'message_date' => $lastMessageDate
            ];

            $setLastMessageDate = LibCron::setLastMessageDate($params);
            $out[] = $setLastMessageDate;
        }

        return $out;
    }

    /**
     * @param $conf
     * @param string $mailboxName
     * @return \Ddeboer\Imap\Mailbox
     */
    protected function getMailBox($conf, $mailboxName = 'INBOX')
    {
        //connect
        $server = new Server($conf->host, $conf->incoming_port, $this->flags);

        $connection = $server->authenticate($conf->login, $conf->password);

        //get mailbox
        $mailbox = $connection->getMailbox($mailboxName);
        return $mailbox;
    }

    /**
     * @param $message
     * @return mixed
     */
    protected function getTo($message)
    {
        $out = [];
        $toArr = $message->getHeaders()->parameters['to'];
        if (is_array($toArr))
            foreach ($toArr as $item) {
                /* @var $item \Ddeboer\Imap\Message\EmailAddress */
                $out[] = $item->getAddress();
            }
        return $out;
    }

    /**
     * @param array $params
     * @return mixed
     */
    protected function getProfiles(array $params)
    {
        $result = LibCron::getProfiles($params);
        if ($result['success'] == true) {
            return $result;
        }
        return false;
    }


    public function test()
    {
        //get new messages
        $newMessages = $this->getNewMessages();

        //save files to db
        foreach ($newMessages as $messageArr) {
            foreach ($messageArr as $message) {
//                d($message['messageObj']);
                $messageObj = $message['messageObj'];
                $insertMessageId = LibDBMongo::insertMessage($message);
                $owner_id = $message['owner_id'];

                if ($this->messageHasAttachments($messageObj)) {
                    $saveFilesToDb = $this->saveFilesToDb($messageObj, $owner_id, $insertMessageId);
                    /* не работает ибо надо переделать всю архитектуру */
                    if ($this->messageHasInlineAttachments($messageObj))
                        $this->saveInlineFilesToDb($messageObj, $owner_id, $insertMessageId);
                    foreach ($messageObj->getAttachments() as $attachment) {
                        AttachmentsHelper::downloadAttachments($attachment);
                    }
                }
                //set last message date to mailbox
                $this->setLastMessageDate();
            }

        }
        d($newMessages);
    }

    /**
     * @param $message
     * @param $arr
     * @return mixed
     */
    protected function getBodyHtml($message)
    {
        try {
            $bodyHtml = $message->getBodyHtml();
        } catch (UnexpectedValueException $exception) {
            $bodyHtml = $message->getContent();
        }
        return $bodyHtml;
    }


    /**
     * @param Message $message
     * @return int
     */
    private function messageHasAttachments(Message $message)
    {
        return count($message->getAttachments() > 0);
    }

    private function saveFilesToDb(Message $message, $owner_id, $message_id)
    {
        $attachmentFilesNames = [];
        foreach ($message->getAttachments() as $attachment) {
            if ($attachment->getDisposition() == 'attachment')
                $attachmentFilesNames[] = AttachmentsHelper::getHashFileName($attachment);
        }

        $result = LibCron::setEmailAttachments([
            'owner_id' => $owner_id,
            'message_id' => $message_id,
            'files_names' => $attachmentFilesNames
        ]);

        if ($result['success'] == true)
            return $attachmentFilesNames;
        return false;
    }

    private function saveInlineFilesToDb(Message $message, $owner_id, $message_id)
    {
        $inlineFiles = [];
        foreach ($message->getAttachments() as $attachment) {
            if ($attachment->getDisposition() == 'inline')
                $inlineFiles[] = AttachmentsHelper::getHashFileName($attachment);
        }

        $result = LibCron::setEmailAttachments([
            'owner_id' => $owner_id,
            'message_id' => $message_id,
            'files_names' => $inlineFiles
        ]);

        if ($result['success'] == true) {
            $i = 0;
            foreach ($message->getAttachments() as $attachment) {
                if ($attachment->getDisposition() == 'inline') {
                    $id = $attachment->getStructure()->id;
                    $file = $result['data']['files'][$i];
                    $html = AttachmentsHelper::findAndReplaceImgPath($id, $file->file_id, $this->html);
                    $this->html = $html;
                    $i++;
                }
            }
            return $inlineFiles;
        }
        return false;
    }

    private function messageHasInlineAttachments(Message $message)
    {
        foreach ($message->getAttachments() as $attachment) {
            if ($attachment->getDisposition() == 'inline')
                return true;
        }
        return false;
    }


}
