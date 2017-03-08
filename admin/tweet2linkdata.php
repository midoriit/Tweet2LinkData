<?php

  // ツイートをMySQLに格納し、LinkData用とマップ用のCSVファイルを出力します
  // cron等を使用して定期的に起動します

  require_once 'vendor/autoload.php';
  use Abraham\TwitterOAuth\TwitterOAuth;

  // 定数定義
  $hashtag = '';                        // ツイートの抽出に用いるハッシュタグ
  $consumer_key = '';                   // Consumer Key (API Key)
  $consumer_secret = '';                // Consumer Secret (API Secret)
  $mysql_server = '';                   // MySQLのサーバー名
  $mysql_database = '';                 // MySQLのデータベース名
  $mysql_username = '';                 // MySQLのユーザ名
  $mysql_password = '';                 // MySQLのパスワード
  $mysql_tablename = '';                // MySQLのテーブル名
  $mapdata = '';                        // マップ用CSVファイル名
  $linkdata = '';                       // LinkData用CSVファイル名

  $query1 = $hashtag.' -RT filter:images';
  try {
    $connection = new TwitterOAuth( $consumer_key, $consumer_secret );
    $tweets = $connection->get( "search/tweets", array('q' => $query1, 'count' => 100) );
  } catch ( Exception $e ) {
    echo 'Twitter search/tweets error: '.$e->getMessage();
    die();
  }

  $mysqli = new mysqli( $mysql_server, $mysql_username, $mysql_password, $mysql_database );

  $found_tweets = 0;
  $new_tweets = 0;
  $invalid_tweets = 0;
  $tweets_exist = 0;

  if ( !$mysqli ) {
    echo $mysqli->error;
    die();
  }

  // Insert Data
  foreach ( $tweets->statuses as $tweet ) {

    if ( $tweet->geo && $tweet->entities->media ) {
    // 緯度経度あり、写真あり

      $query2 = "INSERT INTO ".$mysql_tablename.
        " (tweet_ID, created_at, user_name, screen_name, tweet_text, ".
        "place_full_name, geo_lat, geo_lon, tweet_url, media_url) VALUES (".
        "'".$tweet->id_str."',".
        "str_to_date('".date('Y/m/d H:i:s', strtotime($tweet->created_at))."','%Y/%m/%d %H:%i:%s'),".
        "'".$tweet->user->name."',".
        "'".$tweet->user->screen_name."',".
        "'".$tweet->text."',".
        "'".$tweet->place->full_name."',".
        "cast('".$tweet->geo->coordinates[0]."' AS DECIMAL(20,10)),".
        "cast('".$tweet->geo->coordinates[1]."' AS DECIMAL(20,10)),".
        "'".$tweet->entities->media[0]->url."',".
        "'".$tweet->entities->media[0]->media_url_https."')";
      $ret2 = $mysqli->query( $query2 );

      if ( !$ret2 ) {
        if( $mysqli->errno == 1062 )
        {
          // 既存のツイート
          $tweets_exist++;
        } else {
          // 不明なエラー
          echo $mysqli->error;
        }
      } else {
        // 新しいツイート
        $new_tweets++;
      }

    } else {
      // 緯度経度のない残念なツイート
      $invalid_tweets++;
    }
  }
  $mysqli->commit();
  echo $found_tweets.' tweets found. New tweets = '.$new_tweets.', Tweets already exist = '.$tweets_exist.', Invalid tweets = '.$invalid_tweets;

  // SET geonames IF pname IS NULL
  $query3 = "SELECT tweet_ID,geo_lat,geo_lon FROM ".$mysql_tablename." WHERE invalid != 1 && pname IS NULL";
  $ret3 = $mysqli->query( $query3 );
  if ( !$ret3 ) {
    echo $mysqli->error;
  } else {
    $base_url = 'http://www.finds.jp/ws/rgeocode.php';
    while ( $row = $ret->fetch_assoc() ) {
      $url = $base_url.'?json&lat='.$row["geo_lat"].'&lon='.$row["geo_lon"];
      if( $resp = file_get_contents($url) ) {
        $json = json_decode($resp);
        if( $json->status == '200' ) {
          $section = $json->result->local[0]->section;
        } else if ( $json->status == '202' ) {
          $section = $json->result->aza[0]->name;
        }
        $mname = str_replace( ' ', '', $json->result->municipality->mname );
        $query4 = 'UPDATE '.$mysql_tablename.' SET '.
          'pname = "'.$json->result->prefecture->pname.'",'.
          'mname = "'.$mname.'", section = "'.$section.'" '.
          'WHERE tweet_ID = "'.$row["tweet_ID"].'"';
        $ret4 = $mysqli->query( $query4 );
        if ( !$ret4 ) {
          echo $mysqli->error;
        }
      }
    }
  }
  $mysqli->commit();

  // SET embed_html IF embed_html IS NULL
  $query5 = 'SELECT tweet_ID, embed_html FROM '.$mysql_tablename.' WHERE invalid != 1 && embed_html IS NULL';
  $ret5 = $mysqli->query( $query5 );
  if ( !$ret5 ) {
    echo $mysqli->error;
  } else {
    while ( $row = $ret->fetch_assoc() ) {
      try {
        $oembed = $connection->get( 'statuses/oembed', array(
          'id' => $row["tweet_ID"],
          'omit_script' => true,
          'maxwidth' => '400',
          'hide_media' => 'false',
          'hide_thread' => 'true',
          'lang' => 'ja'
        ));
      } catch ( Exception $e ) {
        echo 'Twitter statuses/oembed error: '.$e->getMessage();
      }
      $query6 = 'UPDATE '.$mysql_tablename.' SET embed_html = "'.rawurlencode($oembed->html).'" WHERE tweet_ID = "'.$row["tweet_ID"].'"';
      $ret6 = $mysqli->query( $query6 );
      if ( !$ret6 ) {
        echo $mysqli->error;
      }
    }
  }
  $mysqli->commit();

  // Export to CSV
  $query7 = "SELECT tweet_ID, DATE_FORMAT(created_at,'%Y-%m-%d') AS date, geo_lat, geo_lon, tweet_url, media_url, pname, mname, section, embed_html FROM ".$mysql_tablename." WHERE invalid != 1";
  $ret7 = $mysqli->query( $query7 );
  if ( !$ret7 ) {
    echo $mysqli->error;
  } else {

    $file1 = fopen( $mapdata, 'w' );
    if($file1 === FALSE) {
      $mysqli->close();
      echo 'Failed to open '.$mapdata;
      die();
    } else {
      fwrite( $file1, 'tweet_ID,lat,lon,embed_html\n' );
    }

    $file2 = fopen($linkdata, 'w');
    if($file2 === FALSE) {
      $mysqli->close();
      fclose( $file1 );
      echo 'Failed to open '.$linkdata;
      die();
    } else {
      fwrite( $file2, 'tweet_ID,created_at,lat,lon,tweet_url,media_url,pname,mname,section,geoname\n" );
    }

    while ( $row = $ret->fetch_assoc() ) {

      // マップ用CSVファイル
      fwrite( $file1,
        $row["tweet_ID"].",".
        $row["geo_lat"].",".
        $row["geo_lon"].",".
        "\"".$row["embed_html"]."\"\n" );

      // LinkData用CSVファイル
      fwrite( $file2,
        "T".$row["tweet_ID"].",".
        $row["date"].",".
        $row["geo_lat"].",".
        $row["geo_lon"].",".
        $row["tweet_url"].",".
        $row["media_url"].",".
        "\"".$row["pname"]."\",\"".$row["mname"]."\",\"".$row["section"]."\",".
        "\"http://geonames.jp/resource/".$row["pname"].$row["mname"].$row["section"]."\"\n" );

    }
    fclose( $file1 );
    fclose( $file2 );
  }
  $mysqli->close();

?>