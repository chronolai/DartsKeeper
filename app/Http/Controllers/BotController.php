<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\Exception\InvalidEventRequestException;
use LINE\LINEBot\Exception\InvalidSignatureException;
use LINE\LINEBot\Exception\UnknownEventTypeException;
use LINE\LINEBot\Exception\UnknownMessageTypeException;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

class BotController extends Controller
{
    public function replyMessage(Request $request)
    {
	    $httpClient = new CurlHTTPClient(env('CHANNEL_TOKEN'));
	    $bot = new LINEBot($httpClient, ['channelSecret' => env('CHANNEL_SECRET')]);

	    $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
	    if (empty($signature)) {
	        Log::Info('Bad Request');
	        return response('Bad Request', 400);
	    }

	    try {
	        $events = $bot->parseEventRequest($request->getContent(), $signature);
	    } catch (InvalidSignatureException $e) {
	        Log::Info('Invalid signature');
	        return response('Invalid signature', 400);
	    } catch (UnknownEventTypeException $e) {
	        Log::Info('Unknown event type has come');
	        return response('Unknown event type has come', 400);
	    } catch (UnknownMessageTypeException $e) {
	        Log::Info('Unknown message type has come');
	        return response('Unknown message type has come', 400);
	    } catch (InvalidEventRequestException $e) {
	        Log::Info("Invalid event request");
	        return response("Invalid event request", 400);
	    }

	    foreach ($events as $event) {
	        if (!($event instanceof MessageEvent)) {
	            Log::Info('Non message event has come');
	            continue;
	        }

	        if (!($event instanceof TextMessage)) {
	            Log::Info('Non text message has come');
	            continue;
	        }

	        $replyText = $event->getText();
	        $resp = $bot->replyText($event->getReplyToken(), $replyText);
	        Log::Info($resp->getHTTPStatus() . ': ' . $resp->getRawBody());
	    }

	    return response("ok", 200);
    }
}
