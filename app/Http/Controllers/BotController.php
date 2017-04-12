<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use LINE\LINEBot\Constant\HTTPHeader;

use App\DartsliveCard;
use App\DartsliveLineBot;

class BotController extends Controller
{
    public function replyMessage(Request $request)
    {
        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
        $content = $request->getContent();

        try {
            $bot = new DartsliveLineBot;
            $messages = $bot->processRequest($signature, $content);
        } catch (Exception $e) {
            return response($e[1], $e[0]);
        }

        foreach ($messages as $message) {
            $bot->replyMessage($message[0], $message[1]);
        }
    }
}
