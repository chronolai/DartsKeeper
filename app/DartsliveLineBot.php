<?php

namespace App;

use Illuminate\Support\Facades\Log;

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\FollowEvent;
use LINE\LINEBot\Event\UnfollowEvent;
use LINE\LINEBot\Event\JoinEvent;
use LINE\LINEBot\Event\LeaveEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\Exception\UnknownEventTypeException;
use LINE\LINEBot\Exception\UnknownMessageTypeException;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

class DartsliveLineBot
{
    private $client;
    private $linebot;
    private $commands;

    public function __construct()
    {
        // @nbn8245a
        $this->client = new CurlHTTPClient(env('CHANNEL_TOKEN'));
        $this->linebot = new LINEBot($this->client, ['channelSecret' => env('CHANNEL_SECRET')]);

        $this->commands = [[
                'name' => 'help',
                'pattern' => 'help',
                'description' => "show help",
            ], [
                'name' => 'info',
                'pattern' => 'info',
                'description' => 'list your cards infomation',
            ], [
                'name' => 'reg',
                'pattern' => 'reg\s(?<card_id>\d+)\s(?<password>\d+)',
                'description' => 'reg card_id paswd',
            ], [
                'name' => 'unreg',
                'pattern' => 'unreg\s(?<card_id>\d+)',
                'description' => 'unreg card_id',
            // ], [
            //     'name' => 'reply',
            //     'pattern' => '(.*)',
            //     'description' => 'reply',
            ]];
    }

    public function pushMessage($line_id='', $message='')
    {
        $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
        $resp = $this->linebot->pushMessage($line_id, $textMessageBuilder);

        Log::Info(sprintf("push [%s] to [%s]", trim(preg_replace('/[\r\n\s]+/', ' ', $message)), $line_id));
        Log::Info(sprintf("resp [%s] %s", $resp->getHTTPStatus(), $resp->getRawBody()));
    }

    public function replyMessage($reply_token='', $message='')
    {
        $resp = $this->linebot->replyText($reply_token, $message);

        Log::Info(sprintf("reply [%s] to [%s]", trim(preg_replace('/[\r\n\s]+/', ' ', $message)), $reply_token));
        Log::Info(sprintf("resp [%s] %s", $resp->getHTTPStatus(), $resp->getRawBody()));
    }

    public function processRequest($signature='', $content='')
    {
        try {
            $events = $this->linebot->parseEventRequest($content, $signature);
        } catch (InvalidSignatureException $e) {
            throw [400, 'Invalid signature'];
        } catch (UnknownEventTypeException $e) {
            throw [400, 'Unknown event type has come'];
        } catch (UnknownMessageTypeException $e) {
            throw [400, 'Unknown message type has come'];
        } catch (InvalidEventRequestException $e) {
            throw [400, "Invalid event request"];
        }

        $messages = [];
        foreach ($events as $event) {
            if (($event instanceof MessageEvent)) {
                $message = $this->handleMessageEvent($event);
            }
            else if (($event instanceof FollowEvent)) {
                $message = $this->handleFollowEvent($event);
            }
            else if (($event instanceof UnfollowEvent)) {
                $message = $this->handleUnfollowEvent($event);
            }
            else if (($event instanceof JoinEvent)) {
                $message = $this->handleJoinEvent($event);
            }
            else if (($event instanceof LeaveEvent)) {
                $message = $this->handleLeaveEvent($event);
            }
            else {
                Log::Info('Non message event has come');
                continue;
            }
            array_push($messages, $message);
        }
        return $messages;
    }

    public function handleMessageEvent($event='')
    {
        if (!($event instanceof TextMessage)) {
            Log::Info('Non text message has come');
            return;
        }

        $command = $this->getCommand($event->getText());
        $func = "run".ucfirst($command['name'])."Command";
        return $this->$func($event, $command);
    }

    public function handleFollowEvent($event='')
    {
        return [$event->getReplyToken(), '請使用 /help 察看說明'];
    }

    public function handleUnfollowEvent($event='')
    {
        return [$event->getReplyToken(), 'UnfollowEvent'];
    }

    public function handleJoinEvent($event='')
    {
        return [$event->getReplyToken(), 'JoinEvent'];
    }

    public function handleLeaveEvent($event='')
    {
        return [$event->getReplyToken(), 'LeaveEvent'];
    }

    public function getCommand($message='')
    {
        foreach ($this->commands as $command) {
            $output = [];
            preg_match('/^'.$command['pattern'].'$/', $message, $output);
            if (count($output) > 0) {
                $command['source'] = $message;
                $command['result'] = $output;
                return $command;
            }
        }
    }

    public function runHelpCommand($event, $command)
    {
        $messages = [];
        foreach ($this->commands as $command) {
            array_push($messages, sprintf("%s\n    %s", $command['name'], $command['description']));
        }
        $message = implode("\n", $messages);
        return [$event->getReplyToken(), $message];
    }

    public function runInfoCommand($event, $command)
    {
        $user_id = $event->getUserId();
        $messages = ["卡片清單:"];
        $cards = DartsliveCard::where('line_id', $user_id)->get();
        foreach ($cards as $card) {
            array_push($messages, sprintf("[%s]\n%s, Rating: %s, Coin: %s\n", $card['card_id'], $card['name'], $card['rating'], $card['coin']));
        }
        $message = implode("\n", $messages);
        return [$event->getReplyToken(), $message];
    }

    public function runRegCommand($event, $command)
    {
        try {
            $user_id = $event->getUserId();
            $site = new DartsliveSite;
            if ($site->login($command['result']['card_id'], $command['result']['password'])) {
	            $card = new DartsliveCard;
	            $card->line_id = $user_id;
	            $card->card_id = $command['result']['card_id'];
	            $card->password = $command['result']['password'];
                $card->name = $site->name;
                $card->rating = $site->rating;
                $card->coin = $site->coin + $site->bonus;
	            $card->save();
	            $message = sprintf('登記卡號 %s ', $card->card_id);
            } else {
	            $message = sprintf('登記失敗 (登入失敗)');
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $err_code = $e->errorInfo[1];
            if($err_code == 1062){
                $message = sprintf('%s 已經被登記過囉！', $card->card_id);
            }
        }
        return [$event->getReplyToken(), $message];
    }

    public function runUnregCommand($event, $command)
    {
        try {
            $user_id = $event->getUserId();
            $card_id = $command['result']['card_id'];
            $card = DartsliveCard::where('line_id', $user_id)->where('card_id', $card_id)->firstOrFail();
            $card->delete();
            $message = sprintf('移除卡號 %s', $card_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $message = sprintf('沒有登記 %s 這張卡喔', $card_id);
        }
        return [$event->getReplyToken(), $message];
    }

    public function runReplyCommand($event, $command)
    {
        $message = $event->getText();
        return [$event->getReplyToken(), $message];
    }
}
