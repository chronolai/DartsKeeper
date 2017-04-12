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
        $cards = DartsliveCard::orderBy('line_id', 'desc')->get();
        foreach ($cards as $card) {
            $site = new DartsliveSite();
            $linebot = new DartsliveLineBot();
            
            $need = $site->login($card['card_id'], $card['password']);
            if ($need) {
                $site->getBonus();
            }

            $card->name = $site->name;
            $card->rating = $site->rating;
            $card->coin = $site->coin + $site->bonus;
            $card->save();

            $linebot->pushMessage($card['line_id'], sprintf("[%s]\n%s, Rating: %s, Coin: %s+%s", $site->card_id, $site->name, $site->rating, $site->coin, $site->bonus));
            $this->line(sprintf("[%s] %s, Rating: %s, Coin: %s+%s", $site->card_id, $site->name, $site->rating, $site->coin, $site->bonus));
        }
    }
}
