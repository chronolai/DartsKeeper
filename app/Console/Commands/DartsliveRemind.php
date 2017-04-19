<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\DartsliveCard;
use App\DartsliveLineBot;

class DartsliveRemind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dartslive:remind';

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
            // if ($card->line_id !== env("ADMIN_LINE_ID")) {
            //     continue;
            // }
            $now = \Carbon\Carbon::now('Asia/Taipei');
            $end = $now->copy()->endOfMonth();
            $days = $end->diffInDays($now);
            $message = sprintf("還剩 %s 天!\n[%s] 還有 %s(%s) Coin, 請記得去購買 theme", $days, $card->card_id, $card->coin, $card->expire);
            $linebot->pushMessage($card->line_id, $message);
            $this->line($message);
        }
    }
}
