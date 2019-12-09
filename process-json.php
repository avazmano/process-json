<?php
require_once('./config.php');
require_once('./savexmlhelperfunctions.php');
require_once('./load_node.php');

if (!file_exists($ndir . "/user_timeline/")) {
    $patterns = array('/^.+\n/', '/\\\u[a-zA-Z0-9]{4}/'); //remove blank lines. single entites -removes remaining entites that sometimes cause blank tweets
} else {
    $patterns = array('/\\\u[a-zA-Z0-9]{4}\\\u[a-zA-Z0-9]{4}/'); // also removes japanese charcters.
}

foreach ($jsfile as $jsUrl) {
    $file = file_get_contents($jsUrl);

    //remove header on json files otherwise causes json to not decode
    $file = preg_replace('/Grailbird\.data\.tweets_[0-9]{4}_[0-9]{2}\s=/', ' ', $file);

    $json_file = json_decode($file, true);

    $alltweets = array_merge($alltweets, $json_file);


}
$field_statuses_count_value = count($alltweets);
fwrite($fp, ' bfFil:' . count($alltweets) . ' ');
$status_text_added_count = $removeRt = $remove_date_start = $remove_date_end = 0;
if (!empty($user_details_file_loc)) {
    $user_details_file = file_get_contents($user_details_file_loc);
    $user_details = json_decode(str_replace('var user_details =', '', $user_details_file), true);
}

if (!file_exists('./' . $nid . '/' . "images") && !empty($twitpics_value)) {
    mkdir('./' . $nid . '/' . "images", 0777, true);
}

$context = array('http' => array('method' => 'GET', 'max_redirects' => 1,),);

$statuses = $alltweets;

//remove duplicates
$statuses = array_map("unserialize", array_unique(array_map("serialize", $statuses)));

$tz_name = timezone_name_from_abbr("", $utc_offset, 0);
date_default_timezone_set($tz_name);

if (!empty($statuses)) {

    // Obtain a list of columns
    foreach ($statuses as $key => $row) {
        $time[$key] = strtotime($row['created_at']);
    }
    //sort by date
    array_multisort($time, SORT_ASC, $statuses);


    $statuses_desc = $statuses;
    array_multisort($time, SORT_DESC, $statuses_desc);

    $statuscount = 0;
    $i = 0;
    $xml = new SimpleXMLElement('<statuses></statuses>');
    $loop_at_year = null;
    $loop_at_date = null;
    $numberof_years = -1;
    $twitpics_array = array();

    $screen_name = $statuses_desc[0]['user']['screen_name'];
    if (!empty($user_details_file)) {
        $description = htmlspecialchars($user_details['bio'], ENT_COMPAT, 'UTF-8');
        $user_info_name = $user_details['full_name'];
        $location = $user_details['location'];
    } else {

        $description = htmlspecialchars($statuses_desc[0]['user']['description'], ENT_COMPAT, 'UTF-8');
        $user_info_name = $statuses_desc[0]['user']['name'];
        $location = $statuses_desc[0]['user']['location'];
        $profile_url = $statuses_desc[0]['user']['entities']['url']['urls'][0]['expanded_url'];
    }

    if (empty($statuses_desc[0]['user'])) {
        $screen_name = $verify_credentials_data['screen_name'];
        $description = $verify_credentials_data['description'];
        $user_info_name = $verify_credentials_data['name'];
        $location = $verify_credentials_data['location'];
        $profile_url = $verify_credentials_data['url'];
    }

    $xml->addChild('name', $user_info_name);
    $exp_date = "2006-01-01";
    $newest_date = strtotime("+1 week");
    $oldest_date = strtotime($exp_date);


    //remove tweets outside dange range
    foreach ($statuses as $value) {
        $created_at_oldest = strtotime($value['created_at']);
        if ($created_at_oldest < $oldest_date || $created_at_oldest > $newest_date) {
            continue;
        } else {
            break;
        }
    }
    foreach ($statuses_desc as $value) {
        $created_at_newest = strtotime($value['created_at']);
        if ($created_at_newest < $oldest_date || $created_at_newest > $newest_date) {
            continue;
        } else {
            break;
        }
    }

    $xml->addChild('profile_image_url', '.' . $ndir . 'profileimage.jpg');
    $xml->addChild('screen_name', $screen_name);
    $xml->addChild('description', $description);
    $xml->addChild('location', $location);
    $xml->addChild('url', $profile_url);

    $dedication = htmlspecialchars($dedication, ENT_COMPAT, 'UTF-8');
    $xml->addChild('dedication', $dedication);
    $photocount = 0;
    foreach ($statuses as $value) {

        $status_text = null;
        $created_at = new DateTime($value['created_at']);
        $photocount++;

        $created_at_time_string = substr($value['created_at'], 11, 18);
        if ($created_at_time_string == '00:00:00 +0000') {
            $created_at_time_notincluded = true;
        } else {
            $created_at_time_notincluded = false;
        }

        $created_at = strtotime($value['created_at']);

        //remove tweets before 2005 and after today
        if ($created_at < $oldest_date || $created_at > $newest_date) {
            continue;
        }

        if (!empty($value['text']) || !empty($value['full_text'])) {

            if (!empty($value['full_text'])) {
                $status_text = $value['full_text'];
            } else {
                $status_text = $value['text'];
            }

            if ($field_remove_urls_value == 2) {
                $status_text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t<]*)#is", ' ', $status_text);
            }
            if ($field_hashtags_value == 2) {
                $status_text = preg_replace("/#(\w+)/", ' ', $status_text);
            }

            //disabled for testing
            if ($htmlspecialchars) {
                $status_text = htmlspecialchars($status_text, ENT_COMPAT);
            }

            if ($removeemoji_andjap) {
                $status_text = preg_replace($patterns, ' ', $status_text);
            }
        }

        /*-------- remove/edit tweets based on selections ------------*/

        //swapp start end values if entered in wrong textbox
        if ($field_date_start_from_value > $field_date_end_from_value && !empty($field_date_start_from_value) && !empty($field_date_end_from_value)) {
            $temp_field_date_start_from_value = $field_date_start_from_value;
            $field_date_start_from_value = $field_date_end_from_value;
            $field_date_end_from_value = $temp_field_date_start_from_value;
        }

        //remove tweets before date
        if (!empty($field_date_start_from_value)) {
            if ($field_date_start_from_value > strtotime($value['created_at'])) {
                $remove_date_start++;
                continue;
            }
        }
        if (!empty($field_date_end_from_value)) {
            $field_date_end_from_value_plus1day = $field_date_end_from_value + 86400;
            if ($field_date_end_from_value_plus1day < strtotime($value['created_at'])) {
                $remove_date_end++;
                continue;
            }
        }
        //remove tweets starting with RT
        if (strpos($status_text, 'RT') === 0 && $field_retweets_value == 'on') {
            $removeRt++;
            continue;
        }

        //remove tweets starting with Just made a book out of my tweets at
        if (strpos($status_text, 'Just made a book out of my tweets at') === 0) {
            continue;
        }

        // all tweets that start with @
        if (strpos($status_text, '@') === 0 && $field_myreplies_value == 'on') {
            continue;
        }

        //remove tweets with user entered strings
        if (!empty($field_exclude_value)) {
            foreach ($remove_tweets_with_array as $value_exclude) {
                $pos = NULL;
                $pos = strpos($status_text, $value_exclude);
                if ($pos === false) {
                } else {
                    break;
                }
            }
            if ($pos === false) {
            } else {
                $pos = true;
                continue;
            }

        }

        $status_text_rtrim = rtrim($status_text);
        if (empty($status_text_rtrim)) {
            continue;
        }

        $statuscount++;

        $status_year = date("Y", $created_at);
        $status_date = date("D M j", $created_at);
        $status_month = date("M", $created_at);
        if ($created_at_time_notincluded) {
            $status_time = '';
        } else {
            $status_time = date("g:ia", $created_at);
        }

        $loop_at_year = $status_year;

        $xmlyear = $xml->addChild('status');
        $xmlyear->addAttribute('year', $status_year);
        $numberof_years++;
        $numberof_dates_in_year = -1;
        if (empty($lastyear)) {
            $lastyear = $status_year;
        }
        $firstyear = $status_year;

    }

    //if date doesnt exist add it
    if ($status_date != $loop_at_date) {
        $loop_at_date = $status_date;

        $xmldate = $xml->status[$numberof_years]->addChild('date');
        $xmldate->addAttribute('date', $status_date);
        $numberof_dates_in_year++;
        if (empty($lastmonth)) {
            $lastmonth = $status_month;
        }
        $firstmonth = $status_month;
    }

    //add status
    if ($status_year == $loop_at_year && $status_date == $loop_at_date) {

        $xmldate = $xml->status[$numberof_years]->date[$numberof_dates_in_year]->
        addChild('tweet');

        if (!empty($status_text)) {
            $xmltext = $xmldate->addChild('text', $status_text);
            $status_text_added_count++;
        }

        $xmldate->addChild('time', $status_time);

        if (!empty($twitpics_value)) {
            $media = $value['entities']['media'];
            if (!empty($media)) {
                foreach ($media as $medias) {


                    $match = $medias['media_url'];

                    if (preg_match('!http://[^?#]+\.(?:jpe?g|png|gif)!Ui', $medias['media_url'])) {
                        if (!in_array($match, $twitpics_array)) {
                            array_push($twitpics_array, $match);
                            array_push($twitpics_array, $medias['url']);
                        } else {
                            continue;
                        }
                        downloadImages($medias['media_url'], $medias['id']);
                    }
                }
            } else {
                $urls = $value['entities']['urls'];
                if (!empty($urls)) {
                    foreach ($urls as $url) {
                        $match = $url['expanded_url'];

                        $img_url = $match;


                        if (strpos($img_url, '/photo/') === false) {

                            $status_text = str_replace($url['url'], $match, $status_text);

                        } else {
                            if (!in_array($match, $twitpics_array)) {
                                array_push($twitpics_array, $match);
                            } else {
                                continue;
                            }
                            $img_url = str_replace('http://twitter.com/', '', $img_url);
                            $m_twitter_page = file_get_contents('https://mobile.twitter.com/' . $img_url);
                            preg_match('/(http:\/\/p.twimg.com\/[^:]+):/i', $m_twitter_page, $matches2);
                            $img_url = array_pop($matches2);

                            if (!empty($img_url)) {
                                downloadImages($img_url);
                            }
                        }
                    }
                    //	}
                }
                if (preg_match_all('#(http|https)(://|://www.)(yfrog|plixi|tweetphoto|moby|instagr|instagram|flic|dailybooth|lockerz|pic.twitter).(com|to|am|kr)/[a-zA-Z0-9-_/]+#',
                    $status_text, $matches)) {
                    foreach ($matches[0] as $match) {
                        if (!in_array($match, $twitpics_array)) {
                            array_push($twitpics_array, $match);
                        } else {
                            continue;
                        }

                        $img_url = $match;

                        if (strpos($match, 'twitpic.com')) {
                            //$img_url = str_replace("twitpic.com", "twitpic.com/show/full", $match);
                            //removed twitpic need to pregmatch img src in response of match url
                        }

                        if (strpos($match, 'yfrog.com')) {
                            $img_url = str_replace("yfrog.com/", "yfrog.com/api/xmlInfo?path=", $match);

                            $yfrogxml = file_get_contents($img_url);

                            if ($yfrogxml != false) {
                                $simpleyfrogxml = simplexml_load_string($yfrogxml);
                                $imagelink = $simpleyfrogxml->links->image_link;
                                $img_url = $imagelink;
                            } else {
                                $img_url = null;
                            }

                        }
                        if (strpos($match, 'moby.to')) {

                            $match = str_replace($findme, "", $match);
                            $mobyxml = file_get_contents('http://api.mobypicture.com/?k=bKNWBMcrdLScfMsm&format=xml&action=getMediaInfo&t=' .
                                $match);

                            //if fail to get image url try again
                            if ($mobyxml == false) {
                                sleep(1);
                                $mobyxml = file_get_contents('http://api.mobypicture.com/?k=bKNWBMcrdLScfMsm&format=xml&action=getMediaInfo&t=' .
                                    $match);
                            }

                            //if imagexml retrieve success replace link
                            if ($mobyxml != false) {

                                $simplemobyxml = simplexml_load_string($mobyxml);
                                $img_url = $simplemobyxml->post->media->url_full;

                            }
                        }

                        if (strpos($match, 'flic.kr/p/')) {

                            $flickrapikey = "f5c392bb323fd8a30ddabcca72044df7";
                            $str = str_replace('http://flic.kr/p/', "", $match);
                            $flickrid = base58_decode($str);
                            $flickrgetSizes = file_get_contents("http://api.flickr.com/services/rest/?method=flickr.photos.getSizes&api_key=" .
                                $flickrapikey . "&photo_id=" . $flickrid);

                            if ($flickrgetSizes !== false) {
                                $simplexmlflickr = simplexml_load_string($flickrgetSizes);
                                $simplexmlflickr_length = count($simplexmlflickr->sizes->children()) - 1;
                                if ($simplexmlflickr_length >= 1) {
                                    if (!empty($simplexmlflickr->sizes->size[$simplexmlflickr_length]->attributes()->source)) {
                                        $img_url = $simplexmlflickr->sizes->size[$simplexmlflickr_length]->attributes()->source;
                                    }
                                }
                            }
                        }

                        if (strpos($match, 'instagr.am/p/')) {
                            $match = str_replace('instagr.am/p/', 'instagram.com/p/', $match);
                        }
                        if (strpos($match, 'instagram.com/p/')) {
                            $img_url = $match . "media/?size=l";

                            @file_get_contents($img_url, null, stream_context_create($context));

                            foreach ($http_response_header as &$value) {
                                if (strpos($value, "ocation: ")) {
                                    $img_url = str_replace("Location: ", "", $value);

                                }
                            }
                        }

                        if (strpos($match, 'dailybooth.com')) {

                            @file_get_contents($match, null, stream_context_create($context));

                            foreach ($http_response_header as &$value) {
                                if (strpos($value, "ocation: ")) {
                                    $dailymotionurl = str_replace("Location: ", "", $value);

                                }
                            }

                            $pieces = explode("/", $dailymotionurl);
                            $dailymotion_id = end($pieces);
                            $dailymotion_json = file_get_contents("http://api.dailybooth.com/v1/pictures/" .
                                $dailymotion_id . ".json");
                            $dailymotion_decoded = json_decode($dailymotion_json);

                            $size = "large";
                            $img_url = $dailymotion_decoded->{'urls'}->{$size};
                        }

                        if (!empty($img_url)) {
                            downloadImages($img_url);
                        }

                    }
                }
            }
        }
    }


}

if (file_exists($nid)) {
} else {
    mkdir($nid, 0755, true);
}

$xml->addChild('firstmonth', $firstmonth);
$xml->addChild('firstyear', $firstyear);
$xml->addChild('lastmonth', $lastmonth);
$xml->addChild('lastyear', $lastyear);
fwrite($fp, ' afFil:' . $status_text_added_count . ' REMOVED(RT' . $removeRt . ' DateBe' . $remove_date_start . ' DateAf:' . $remove_date_end . ') ');
$xml->asXML($nid . "/book-1.xml");

} else {

    error_exit($nid, $vid, $fp, $tempconvertingfile);
}

?>