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
use Ddeboer\Transcoder\Exception\UnsupportedEncodingException;
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

    public function getNewMessages()
    {
        $inArray = [];
        $i = 0;

        foreach ($this->userSettings as $userSetting) {
            $i++;

            $conf = $userSetting->settings->email_conf;

            //get messages
            $messages = $this->getAllMessages($conf, $conf->last_message_date);
//            continue;
            foreach ($messages as $messageArr) {
                $message = $messageArr['message'];
                /* @var $message Message */

                $message->keepUnseen(true);
                $messageParser = new MessageParser($message);

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

                if ($profiles) {
                    $inArray['profiles'] = $profiles['data']['profiles'];
                    $inArray['owner_id'] = (int)$userSetting->owner_id;
                    $inArray['message_subject'] = $message->getSubject();
                    $inArray['message_content'] = $messageParser->clearMessage();
                    $inArray['message_content_full'] = $this->getBodyHtml($message);
                    $inArray['message_date'] = $message->getDate()->getTimestamp();
                    $inArray['utc_timezone'] = $message->getHeaders()->get('date')->format('P');
                    $inArray['was_read'] = $message->isSeen();
                    $inArray['parsing_message_id'] = $message->getId();
                    $inArray['message_direction'] = $profiles['data']['message_direction'];
                    $inArray['message_obj'] = $message;
                    $out[] = $inArray;
                }

                $this->setLastMessageDate($userSetting, $message->getDate()->getTimestamp());
            }

            if (!empty($out)) {
                $outExt[] = $out;
            }
        }

        if (!empty($outExt)) {
            return $outExt;
        } else
            return [];
    }

    public function setLastMessageDate($userSetting, $timestamp)
    {
        LibDBMongo::log($userSetting, $timestamp);

        if (!$timestamp)
            throw new Exception('timestamp is required');

        //set last message date
        $params = [
            'user_id' => $userSetting->user_id,
            'profile_id' => $userSetting->profile_id,
            'owner_id' => $userSetting->owner_id,
            'message_date' => $timestamp
        ];
        d($params);
        $setLastMessageDate = LibCron::setLastMessageDate($params);
    }

    /**
     * @param $conf
     * @param null $last_message_date
     * @return array
     */
    protected function getAllMessages($conf, $last_message_date = null)
    {
        //connect
        $server = new Server($conf->host, $conf->incoming_port, $this->flags);
        $connection = $server->authenticate($conf->login, $conf->password);

        //если дата не установлена находим послденее письмо
        if (!$last_message_date) {

            $preOut = $this->_getAllMessages($connection);
            $last = end($preOut);

            //устанавливаем последнюю дату этим письмом
            $last_message_date = $last['date'];
        }

        //найти все письма поздее это даты
        $dateTime = new DateTime();
        $dateTime->setTimestamp($last_message_date);

        //парамерты поиска письма
        $searchExpression = new SearchExpression();
        $searchExpression->addCondition(new After($dateTime));

        $out = $this->_getAllMessages($connection, $searchExpression);

        return $out;
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


    public function start()
    {
        //get new messages
        $newMessages = $this->getNewMessages();
        d($newMessages);
        die('start');
//        save files to db
        foreach ($newMessages as $messageArr) {
            foreach ($messageArr as $message) {
                $insertMessage = LibDBMongo::insertMessage($message);
                /* @var $message_obj Message */
                $message_obj = $message['message_obj'];

                if ($insertMessage['success'] == true)
                    if ($this->messageHasAttachments($message_obj)) {
                        $attachmentFilesNames = $this->getAttachmentFilesNames($message_obj);
                        $what = LibCron::setEmailAttachments([
                            'owner_id' => $message['owner_id'],
                            'message_id' => $insertMessage['message_id'],
                            'files_names' => $attachmentFilesNames
                        ]);

                        $this->downLoadIFiles($message_obj);
                    }
            }
        }
    }

    protected function getAttachmentFilesNames(Message $message)
    {
        $out = [];
        foreach ($message->getAttachments() as $attachment) {
            $out[] = (object)[
                'file_name' => AttachmentsHelper::getHashFileName($attachment),
                'src' => trim($attachment->getStructure()->id, '<>')
            ];
        }
        return $out;
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

    /**
     * @param $message_obj
     * @return mixed
     */
    protected function getInlineFiles($message_obj)
    {
        $inlineFiles = [];
        foreach ($message_obj->getAttachments() as $attachment) {
            if ($attachment->getDisposition() == 'inline') {
                $id = $attachment->getStructure()->id;
                $clearId = trim($id, '<>');

                $inlineFiles[] = [
                    'file_name' => AttachmentsHelper::getHashFileName($attachment),
                    'src' => $clearId
                ];
            }
        }
        LibDBMongo::log("inline_files: {$inlineFiles}");
        return $inlineFiles;
    }

    /**
     * @param $message_obj
     */
    protected function downLoadIFiles($message_obj)
    {
        foreach ($message_obj->getAttachments() as $attachment) {
            AttachmentsHelper::downloadAttachments($attachment);
        }
    }

    /**
     * @param $connection
     * @param $searchExpression
     * @param $inArr
     * @param $preOut
     * @return array
     */
    protected function _getAllMessages($connection, $searchExpression = null)
    {
        $inArr = [];
        $out = [];
        foreach ($connection->getMailboxes() as $mailbox) {
            /* @var $mailbox \Ddeboer\Imap\Mailbox */
            $i = 0;
            try {
                foreach ($mailbox->getMessages($searchExpression) as $message) {
                    $i++;
//                    if ($i == 3)
//                        break;
                    $date = $message->getDate() ? $message->getDate()->getTimestamp() : 0;

                    $inArr['date'] = $date;
                    $inArr['message'] = $message;

                    $out[] = $inArr;
                }

            } catch (UnsupportedEncodingException $exception) {
                LibDBMongo::log($exception->getMessage());
            }
        }

        d(count($out));
        die('ss');
        //get column date
        foreach ($out as $key => $row)
            $dateArr[$key] = $row['date'];

        //отсортировать по дате
        if (is_array($dateArr))
            array_multisort($dateArr, SORT_ASC, $out);
        return $out;
    }

}
