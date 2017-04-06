<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LinePushMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "line:push {message} {line_id?}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push message to Line';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $message = $this->argument('message');
        $line_id = $this->argument('line_id');
        $line_id = $line_id ? $line_id : env('ADMIN_LINE_ID');

        $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(env('CHANNEL_TOKEN'));
        $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => env('CHANNEL_SECRET')]);
        $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
        $response = $bot->pushMessage($line_id, $textMessageBuilder);

        $this->line(sprintf("send [%s] to [%s]", $message, $line_id));
        $this->line(sprintf("resp [%s] %s", $response->getHTTPStatus(), $response->getRawBody()));
    }
}
