<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\Notifier\NullNotifier;
use Joli\JoliNotif\NotifierFactory;

class Btcaddrinfo extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'btcinfo:get';
//        {--addr : BTC address}
//        {--confirmations: confirmations}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Get BTC address info';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $notifier      = NotifierFactory::create();
        $addr          = '3BMEX2NLbpXRvq3Jm9RPSs1qG8g1QG9Ghn';
//	$addr          = '36MnyqLWMK1mj3f7rLgvb4F4uif1w716Lg';
        $confirmations = 2;

        try {
            $balance_cur = file_get_contents('https://blockchain.info/q/addressbalance/' . $addr . '?confirmations=' . $confirmations);
            //$balance_ttl = file_get_contents('https://blockchain.info/q/addressbalance/' . $addr);
            $balance_ttl = file_get_contents('https://blockchain.info/q/getreceivedbyaddress/' . $addr);
        } catch (\Exception $exception) {
            $this->error('Can\'t get data');
        }

        $hasUnconfirmedTransaction = $balance_ttl - $balance_cur;


        if (!($notifier instanceof NullNotifier) && $hasUnconfirmedTransaction) {
            $notification =
                (new Notification())
                    ->setTitle('BTC address info')
                    ->setBody('Unconfirmed transaction: ' . $balance_cur / 10000000)
                    ->setIcon(__DIR__.'/icon-success.png')
            ;

            $result = $notifier->send($notification);

            echo 'Notification ', $result ? 'successfully sent' : 'failed', ' with ', get_class($notifier), PHP_EOL;
        } else {
            echo 'No supported notifier', PHP_EOL;
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
