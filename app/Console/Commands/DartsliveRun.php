<?php

namespace App\Console\Commands;

use Artisan;
use Illuminate\Console\Command;

use App\DartsliveCard;
use App\DartsliveSite;
use App\DartsliveLineBot;

class DartsliveRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dartslive:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $linebot = new DartsliveLineBot();
        $cards = DartsliveCard::orderBy('line_id', 'desc')->get();
        foreach ($cards as $card) {
            $site = new DartsliveSite();

            $result = $site->login($card['card_id'], $card['password']);
            if ($result && $site->need) {
                $site->getBonus();
            }

            $card->name = $site->name;
            $card->rating = $site->rating;
            $card->coin = $site->coin + $site->bonus;
            $card->expire = $site->expire;
            $card->save();

            $message = sprintf("[%s]\n%s, Rating: %s, Coin: %s(%s)+%s", $site->card_id, $site->name, $site->rating, $site->coin, $site->expire, $site->bonus);
            $linebot->pushMessage($card->line_id, $message);
            $this->line($message);
        }
    }
}
