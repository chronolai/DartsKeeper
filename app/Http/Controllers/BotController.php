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

class BotController extends Controller
{
    public function __construct()
    {
        $this->client = new CurlHTTPClient(env('CHANNEL_TOKEN'));
        $this->bot = new LINEBot($this->client, ['channelSecret' => env('CHANNEL_SECRET')]);
        $this->commands = [[
                'name' => 'help',
                'pattern' => '\/help',
                'description' => 'help',
            ], [
                'name' => 'info',
                'pattern' => '\/info',
                'description' => 'info',
            ], [
                'name' => 'reg',
                'pattern' => '\/reg\s(\d+)\s(\d+)',
                'description' => 'reg',
            ], [
                'name' => 'unreg',
                'pattern' => '\/unreg',
                'description' => 'unreg',
            ], [
                'name' => 'reply',
                'pattern' => '(.*)',
                'description' => 'reply',
            ]];
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

    public function handleMessageEvent($event='')
    {
        if (!($event instanceof TextMessage)) {
            Log::Info('Non text message has come');
            return;
        }

        $command = $this->processCommands($event->getText());

        $user_id = $event->getUserId();
        $message = sprintf('[%s] %s', $command['name'], $command['description']);

        $resp = $this->bot->replyText($event->getReplyToken(), $message);
        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
    }

    public function handleFollowEvent($event='')
    {
        $message = sprintf('follow');
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
