<?php

namespace boombatower\dotabufftoical;

use DateTime;
use DateTimeZone;

use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;

use GuzzleHttp\Client;

use Symfony\Component\DomCrawler\Crawler;

require 'vendor/autoload.php';

// Such validation, much security.
if (empty($_GET['player'])) {
  die('player must not be empty');
}
$player = (int) $_GET['player'];

$client = new Client([
  'base_uri' => 'http://www.dotabuff.com',
  'headers' => [
    // Dotabuff trolls bots.
    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.106 Safari/537.36',
  ],
]);
$response = $client->get($id = 'players/' . $player . '/matches');
if ($response->getStatusCode() != 200) {
  die('dotabuff hates life');
}

$html = $response->getBody();
$crawler = new Crawler((string) $html);

// Extract match start times and durations.
// Two separate xpaths instead of loop over rows since crawler is convoluted
// when compared to simplexml...which I should have just used.
$dates = $crawler->filterXPath('//table[2]//time')->each(function(Crawler $item) {
  return $item->attr('datetime');
});

$durations = $crawler->filterXPath('//table[2]//tr/td[6]')->each(function(Crawler $item) {
  return $item->text();
});

// Build iCal object.
$calendar = new Calendar($id);

$zone = new DateTimeZone('UTC');
foreach ($dates as $i => $date) {
  $start = DateTime::createFromFormat(DATE_ATOM, $date, $zone);

  $duration = $durations[$i];
  $seconds = 0;
  $parts = array_reverse(explode(':', $duration));
  foreach ($parts as $exponent => $part) {
    $seconds += $part * (pow(60, $exponent));
  }
  $end = clone $start;
  $end->modify('+' . $seconds . ' seconds');

  $event = new Event();
  $event->setDtStart($start);
  $event->setDtEnd($end);
  $event->setSummary($duration);
  $calendar->addComponent($event);
}

// Export data.
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="cal.ics"');
echo $calendar->render();
