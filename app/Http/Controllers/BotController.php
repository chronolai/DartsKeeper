<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

use App\DartsliveCard;

class BotController extends Controller
{
    public function __construct()
    {
        $this->client = new CurlHTTPClient(env('CHANNEL_TOKEN'));
        $this->bot = new LINEBot($this->client, ['channelSecret' => env('CHANNEL_SECRET')]);
        $this->commands = [[
                'name' => 'help',
                'pattern' => '\/help',
                'description' => 'show help',
            ], [
                'name' => 'info',
                'pattern' => '\/info',
                'description' => 'list your cards infomation',
            ], [
                'name' => 'reg',
                'pattern' => '\/reg\s(?<card_id>\d+)\s(?<password>\d+)',
                'description' => '/reg 1234567890123456 1234',
            ], [
                'name' => 'unreg',
                'pattern' => '\/unreg\s(?<card_id>\d+)',
                'description' => '/unreg 1234567890123456',
            ], [
                'name' => 'reply',
                'pattern' => '(.*)',
                'description' => 'reply',
            ]];
    }

    public function processCommands($message='')
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

    public function replyMessage(Request $request)
    {
        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
        if (empty($signature)) {
            return response('Bad Request', 400);
        }

        try {
            $events = $this->bot->parseEventRequest($request->getContent(), $signature);
        } catch (InvalidSignatureException $e) {
            return response('Invalid signature', 400);
        } catch (UnknownEventTypeException $e) {
            return response('Unknown event type has come', 400);
        } catch (UnknownMessageTypeException $e) {
            return response('Unknown message type has come', 400);
        } catch (InvalidEventRequestException $e) {
            return response("Invalid event request", 400);
        }

        foreach ($events as $event) {
            if (($event instanceof MessageEvent)) {
                $this->handleMessageEvent($event);
            }
            else if (($event instanceof FollowEvent)) {
                $this->handleFollowEvent($event);
            }
            else if (($event instanceof UnfollowEvent)) {
                $this->handleUnfollowEvent($event);
            }
            else if (($event instanceof JoinEvent)) {
                $this->handleJoinEvent($event);
            }
            else if (($event instanceof LeaveEvent)) {
                $this->handleLeaveEvent($event);
            }
            else {
                Log::Info('Non message event has come');
                continue;
            }
        }

        return response("ok", 200);
    }

    public function handleMessageEvent($event='')
    {
        if (!($event instanceof TextMessage)) {
            Log::Info('Non text message has come');
            return;
        }

        $command = $this->processCommands($event->getText());

        $user_id = $event->getUserId();
        $message = sprintf('[%s] %s', $command['name'], $command['description']);

        if ($command['name'] === "help") {
            $message = '';
            foreach ($this->commands as $command) {
                $message.= $command['name']."\n";
                $message.= "    ".$command['description']."\n";
            }
        }

        if ($command['name'] === "info") {
            $message = "卡片清單: \n";
            $cards = DartsliveCard::where('line_id', $user_id)->get();
            foreach ($cards as $card) {
                $message.= $card['card_id']."\n";
            }
        }

        if ($command['name'] === "reg") {
            try {
                $card = new DartsliveCard;
                $card->line_id = $user_id;
                $card->card_id = $command['result']['card_id'];
                $card->password = $command['result']['password'];
                $card->save();
                $message = sprintf('登記卡號 %s ', $card->card_id);
                Log::Info(sprintf('[reg] %s, %s, %s', $card->line_id, $card->card_id, $card->password));
            } catch (\Illuminate\Database\QueryException $e) {
                $errorCode = $e->errorInfo[1];
                if($errorCode == 1062){
                    $message = sprintf('%s 已經被登記過囉！', $card->card_id);
                    Log::Error(sprintf('[error] %s duplicate', $card->card_id, $card->password));
                }
            }
        }

        if ($command['name'] === "unreg") {
            try {
                $card_id = $command['result']['card_id'];
                $card = DartsliveCard::where('line_id', $user_id)->where('card_id', $card_id)->firstOrFail();
                $card->delete();
                $message = sprintf('移除卡號 %s', $card_id);
                Log::Info(sprintf('[unreg] %s, %s', $user_id, $card_id));
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $message = sprintf('沒有登記 %s 這張卡喔', $card_id);
                Log::Info(sprintf('[unreg]  %s, %s', $user_id, $card_id));
            }
        }

        $resp = $this->bot->replyText($event->getReplyToken(), $message);
        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }

    public function handleFollowEvent($event='')
    {
        $message = sprintf('請使用 /help 察看說明');
        $resp = $this->bot->replyText($event->getReplyToken(), $message);
        Log::Info('handleFollowEvent');
        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }

    public function handleUnfollowEvent($event='')
    {
        $message = sprintf('unfollow');
        $resp = $this->bot->replyText($event->getReplyToken(), $message);
        Log::Info('handleUnfollowEvent');
        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }

    public function handleJoinEvent($event='')
    {
        $message = sprintf('join');
        $resp = $this->bot->replyText($event->getReplyToken(), $message);
        Log::Info('handleJoinEvent');
        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }

    public function handleLeaveEvent($event='')
    {
        $message = sprintf('leave');
        $resp = $this->bot->replyText($event->getReplyToken(), $message);
        Log::Info('handleLeaveEvent');
        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }
}
