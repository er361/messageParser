<?php
/**
 * Created by PhpStorm.
 * User: cs-user2017
 * Date: 1/16/2018
 * Time: 4:29 PM
 */
require __DIR__ . "/vendor/autoload.php";

use Ddeboer\Imap\Exception\AuthenticationFailedException;
use Ddeboer\Imap\Search\Email\To;
use Ddeboer\Imap\Search\Text\Subject;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Mailbox;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Email\FromAddress;
use MColl\helpers\AttachmentsHelper;
use MColl\helpers\FileHelper;
use Ddeboer\Imap\Message;
use MColl\helpers\MessageParser;

/////
$username = 'alexandr.s@capstone.kz';
$password = '5s(LeDOkRV/e<Q/';
$host = 'just111.justhost.com';
//tester
//$username = 'tester@planizator.com';
//$password = '5"%NTz(-Mmo';
//$host = 'just187.justhost.com';

///////

$dateTime = new DateTime();
$dateTime->setTimestamp(1516699643);

//search
$search = new \Ddeboer\Imap\SearchExpression();
$search->addCondition(new \Ddeboer\Imap\Search\Date\After($dateTime));
$search->addCondition(new Subject('приглашаем на курсы в г. Астану '));

//get messages

//connect
$server = new Server($host, '993', '/imap/ssl/novalidate-cert');

$connection = $server->authenticate($username, $password);

//get mailbox
$mailbox = $connection->getMailbox('INBOX');


$messageIterator = $mailbox->getMessages($search);

foreach ($messageIterator as $message) {
    $getTo = getTo($message->getTo());
    d($getTo);
    if (false) {
        d($message->getHeaders());
        $mHelper = new MessageParser($message);
        $clearMessage = $mHelper->clearMessage();
        if (false) {
            $attachmentHelper = new AttachmentsHelper($clearMessage);

            d($message->getParts());
            foreach ($message->getAttachments() as $attachment) {
                $attachmentHelper->saveAttachment($attachment, $message->getId());
            }
            echo '<div style = "border: 4px solid #1ca523;">' . $attachmentHelper->getHtml() . '</div>';
        }
        echo '<div style = "border: 4px dashed darkgreen;">' . $clearMessage . '</div>';
        echo "<h2>{$mHelper->getID()}</h2>";
    } else {
        d($message->getHeaders());
        try {
            echo $message->getBodyHtml();
            echo '<hr>';
            echo $message->getBodyText();
        } catch (UnexpectedValueException $exception) {
            echo $message->getContent();
        }
    }
}

function getTo(array $to){
    $out = [];
    foreach ($to as $recepients) {
        /* @var $recepients Message\EmailAddress */
        $out[] = $recepients->getAddress();
    }
    return $out;
}
