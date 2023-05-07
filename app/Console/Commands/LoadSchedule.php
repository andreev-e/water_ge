<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\ServiceCenter;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\HtmlNode;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;
use function PHPUnit\Framework\exactly;

class LoadSchedule extends Command
{
    protected $signature = 'load-schedule';

    protected $description = 'Load water schedule';

    /**
     * Execute the console command.
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(Client $client, Dom $dom): void
    {

        for ($i = 0; $i < 1; $i++) {
            $url = 'http://water.gov.ge/page/full/107' . ($i * 10 === 0 ? '' : '/' . $i * 10);
            echo $url . PHP_EOL;
            $response = $client->get($url);

            try {
                $dom->loadStr($response->getBody()->getContents());
                $events = $dom->find('#accordion')?->getChildren();

                /* @var $event \PHPHtmlParser\Dom\HtmlNode */
                foreach ($events as $event) {
                    $addresses = [];
                    $title = $event->find('.panel-title')?->getChildren()[1]->text();
                    $serviceCenter = explode(' - ', $title)[0];

                    /* @var $child \PHPHtmlParser\Dom\HtmlNode */
                    foreach ($event->find('.panel-body .row .col-sm-12')?->getChildren() as $child) {

                        if (strpos($child->text(), 'წყალმომარაგების შეწყვეტის დრო:')) {
                            $from = trim(explode(': ', $child->text())[1]);
                            continue;
                        }
                        if (strpos($child->text(), 'წყალმომარაგების აღდგენის დრო:')) {
                            $to = trim(explode(': ', $child->text())[1]);
                            continue;
                        }

                        if (get_class($child) === HtmlNode::class && $child->hasChildren()) {
                            foreach ($child->getChildren() as $childChild) {
                                if (trim($childChild->text) && !$this->isEnding(trim($childChild->text))) {
                                    $addresses[] = trim($childChild->text);
                                }
                            }
                        }
                    }

//                    dump($serviceCenter, $from, $to);

                    $serviceCenter = ServiceCenter::query()->firstOrCreate(['name' => $serviceCenter]);

                    $event = Event::query()->firstOrCreate([
                        'service_center_id' => $serviceCenter->id,
                        'start' => Carbon::createFromFormat('d/m/Y H:i:s', $from),
                        'finish' => Carbon::createFromFormat('d/m/Y H:i:s', $to),
                    ]);

                    foreach ($addresses as $address) {
                        /* @var $addressObject \App\Models\Address */
                        $addressObject = $serviceCenter->addresses()->firstOrCreate(['name' => $address]);
                        $addressObject->events()->syncWithoutDetaching($event);
                    }
                }
            } catch (ChildNotFoundException|CircularException|StrictException|NotLoadedException $e) {
            }
        }
    }

    private function isEnding(string $address): bool
    {
        return strpos(' ' . $address, 'წყალმომარაგების შეზღუდვა გამოწვეულია ტექნიკური სამუშაოების გათვალისწინებით') ||
            strpos(' ' . $address, 'გამორთული მისამართები') ||
            strpos(' ' . $address,
                'შ.პ.ს. საქართველოს გაერთიანებული წყალმომარაგების კომპანია ბოდიშს უხდის მომხმარებლებს შექმნილი დისკომფორტის გამო');
    }
}



