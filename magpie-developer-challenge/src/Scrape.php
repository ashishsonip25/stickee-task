<?php

namespace App;

require '../vendor/autoload.php';
use DateSearch;
use Symfony\Component\DomCrawler\Crawler;

class Scrape
{
    private array $products = [];
    private array $colours = [];
    private int $i = 0;
    private string $baseUrl = 'https://www.magpiehq.com/developer-challenge/';
    
    
    public function run(): void
    {
        $paginationCount    = 3;
        for ( $pagination = 1; $pagination <= $paginationCount; $pagination++) { 
            $url = "https://www.magpiehq.com/developer-challenge/smartphones/?page=".$pagination;
            $html               = file_get_contents( $url );
            $crawler            = new Crawler( $html );
            $availabilityText   = '';
            $productTitle       = $crawler->filter('div.product div.bg-white')->each(function( Crawler $product, $index ){
                unset($this->colours);
                // fetch all colours from product
                $product->filter('div.rounded-md > div.my-4 > div.flex div > span')->each( function( $colour, $colourIndex ){                
                    $this->colours[]         = $colour->extract(array('data-colour'));   
                });
                
                if( count( $this->colours ) ) {
                    foreach ($this->colours as $colour) {
                        // check product is already exist with same name & same colour
                        $productExistFlag   = false;
                        foreach( $this->products as $existProducts ) {
                            if( $existProducts['title'] == $product->filter('h3')->text() && $existProducts['colour'] == $colour[0] ) {
                                $productExistFlag = true;
                            }
                        }
                        if( !$productExistFlag ) {
                            // Prepare array of products
                            $this->products[$this->i]['title']      = $product->filter('h3')->text();
                            $this->products[$this->i]['price']      = $product->filter('div.rounded-md > div')->eq(1)->count() ? preg_replace("/[^0-9\.]/", "", $product->filter('div.rounded-md > div')->eq(1)->text()) : '';
                            $this->products[$this->i]['imageUrl']   = $this->baseUrl . str_replace( '../', '', $product->filterXpath('//img')->extract(array('src'))[0]);
                            $this->products[$this->i]['capacityMB'] = (int)filter_var($product->filter('span.product-capacity')->text(), FILTER_SANITIZE_NUMBER_INT) * 1000;
                            $this->products[$this->i]['colour']     = count($colour) ? $colour[0] : '';
                            $this->products[$this->i]['availabilityText'] = $product->filter('div.rounded-md > div')->eq(2)->count() ? trim(str_replace('Availability:', '', $product->filter('div.rounded-md > div')->eq(2)->text())) : '';
                            
                            if( $this->products[$this->i]['availabilityText'] != '' && str_contains( $this->products[$this->i]['availabilityText'], 'In Stock' ) == true ) {
                                $this->products[$this->i]['isAvailable'] = 'true';
                            } else {
                                $this->products[$this->i]['isAvailable'] = 'false';
                            }

                            $this->products[$this->i]['shippingText'] = $product->filter('div.rounded-md > div')->eq(3)->count() ? $product->filter('div.rounded-md > div')->eq(3)->text() : '';
                            $shippingDate = $this->products[$this->i]['shippingText'] != '' ? $this->findDate($this->products[$this->i]['shippingText']) : '';
                            $this->products[$this->i]['shippingDate'] = $shippingDate != '' && $shippingDate['year'] != '' && $shippingDate['month'] != '' && $shippingDate['day'] != '' ? implode('-', $shippingDate) : '';
                            $this->i++;  
                        }             
                    }                
                }
            });
        }
        
        file_put_contents('output.json', json_encode($this->products));
    }

    public function findDate( $string ) {
        $shortenize = function( $string ) {
            return substr( $string, 0, 3 );
        };

        // Define month name:
        $month_names = array(
            "january",
            "february",
            "march",
            "april",
            "may",
            "june",
            "july",
            "august",
            "september",
            "october",
            "november",
            "december"
        );

        $short_month_names = array_map( $shortenize, $month_names );

        // Define day name
        $day_names = array(
            "monday",
            "tuesday",
            "wednesday",
            "thursday",
            "friday",
            "saturday",
            "sunday"
        );
        $short_day_names = array_map( $shortenize, $day_names );

        // Define ordinal number
        $ordinal_number = ['st', 'nd', 'rd', 'th'];

        $day = "";
        $month = "";
        $year = "";

        // Match dates: 01/01/2012 or 30-12-11 or 1 2 1985
        preg_match( '/([0-9]?[0-9])[\.\-\/ ]+([0-1]?[0-9])[\.\-\/ ]+([0-9]{2,4})/', $string, $matches );
        if ( $matches ) {
            if ( $matches[1] )
                $day = $matches[1];
            if ( $matches[2] )
                $month = $matches[2];
            if ( $matches[3] )
                $year = $matches[3];
        }

        // Match dates: Sunday 1st March 2024; Sunday, 1 March 2024; Sun 1 Mar 2024; Sun-1-March-2024
        preg_match('/(?:(?:' . implode( '|', $day_names ) . '|' . implode( '|', $short_day_names ) . ')[ ,\-_\/]*)?([0-9]?[0-9])[ ,\-_\/]*(?:' . implode( '|', $ordinal_number ) . ')?[ ,\-_\/]*(' . implode( '|', $month_names ) . '|' . implode( '|', $short_month_names ) . ')[ ,\-_\/]+([0-9]{4})/i', $string, $matches );
        if ( $matches ) {
            if ( empty( $day ) && $matches[1] )
                $day = $matches[1];

            if ( empty( $month ) && $matches[2] ) {
                $month = array_search( strtolower( $matches[2] ),  $short_month_names );

                if ( ! $month )
                    $month = array_search( strtolower( $matches[2] ),  $month_names );

                $month = $month + 1;
            }

            if ( empty( $year ) && $matches[3] )
                $year = $matches[3];
        }

        // Match dates: March 1st 2024; March 1 2024; March-1st-2024
        preg_match('/(' . implode( '|', $month_names ) . '|' . implode( '|', $short_month_names ) . ')[ ,\-_\/]*([0-9]?[0-9])[ ,\-_\/]*(?:' . implode( '|', $ordinal_number ) . ')?[ ,\-_\/]+([0-9]{4})/i', $string, $matches );
        if ( $matches ) {
            if ( empty( $month ) && $matches[1] ) {
                $month = array_search( strtolower( $matches[1] ),  $short_month_names );

                if ( ! $month )
                    $month = array_search( strtolower( $matches[1] ),  $month_names );

                $month = $month + 1;
            }

            if ( empty( $day ) && $matches[2] )
                $day = $matches[2];

            if ( empty( $year ) && $matches[3] )
                $year = $matches[3];
        }

        // Match month name:
        if ( empty( $month ) ) {
            preg_match( '/(' . implode( '|', $month_names ) . ')/i', $string, $matches_month_word );
            if ( $matches_month_word && $matches_month_word[1] )
                $month = array_search( strtolower( $matches_month_word[1] ),  $month_names );

            // Match short month names
            if ( empty( $month ) ) {
                preg_match( '/(' . implode( '|', $short_month_names ) . ')/i', $string, $matches_month_word );
                if ( $matches_month_word && $matches_month_word[1] )
                    $month = array_search( strtolower( $matches_month_word[1] ),  $short_month_names );
            }

            if( $month != '' ) {
                $month = $month + 1;    
            } else {
                $month = 1;
            }

        }

        // Match 5th 1st day:
        if ( empty( $day ) ) {
            preg_match( '/([0-9]?[0-9])(' . implode( '|', $ordinal_number ) . ')/', $string, $matches_day );
            if ( $matches_day && $matches_day[1] )
                $day = $matches_day[1];
        }

        // Match Year if not already setted:
        if ( empty( $year ) ) {
            preg_match( '/[0-9]{4}/', $string, $matches_year );
            if ( $matches_year && $matches_year[0] )
                $year = $matches_year[0];
        }
        if ( ! empty ( $day ) && ! empty ( $month ) && empty( $year ) ) {
            preg_match( '/[0-9]{2}/', $string, $matches_year );
            if ( $matches_year && $matches_year[0] )
                $year = $matches_year[0];
        }

        // Day leading 0
        if ( 1 == strlen( $day ) )
            $day = '0' . $day;

        // Month leading 0
        if ( 1 == strlen( $month ) )
            $month = '0' . $month;

        // Check year:
        if ( 2 == strlen( $year ) && $year > 20 )
            $year = '19' . $year;
        else if ( 2 == strlen( $year ) && $year < 20 )
            $year = '20' . $year;

        $date = array(
            'year'  => $year,
            'month' => $month,
            'day'   => $day
        );

        // Return false if nothing found:
        if ( empty( $year ) && empty( $month ) && empty( $day ) )
            return false;
        else
            return $date;
    }
}

$scrape = new Scrape();
$scrape->run();
