<?php defined('BASEPATH') OR exit('No direct script access allowed');

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;



class Webhook extends CI_Controller {

  private $bot;
  private $events;
  private $signature;
  private $user;
  private $httpClient;

  function __construct()
  {
    parent::__construct();
    $this->load->model('tebakkode_m');
 
    // create bot object ($this digunakan untuk mengakses anggota kelas di dalam lingkukngan kelas) (-> untuk mengakses anggota objek)
    $this->$httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $this->bot  = new LINEBot($this->$httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
    // $this-> digunkan untuk mengakses anggota objek yg masih berada dalam anggota kelas
  }

  public function index()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo "Hello Kamu sedang mengakses laman /index()";
      header('HTTP/1.1 400 Only POST method allowed');
      exit;
    }
 
    // get request
    $body = file_get_contents('php://input');
    $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : "-";
    $this->events = json_decode($body, true);
 
    // save log every event requests
    $this->tebakkode_m->log_events($this->signature, $body);
    
    // menyaring log dari user saja bukan dari group
    if(is_array($this->events['events'])){
      foreach ($this->events['events'] as $event){
 
        // skip group and room event
        if(! isset($event['source']['userId'])) continue;
 
        // get user data from database
        $this->user = $this->tebakkode_m->getUser($event['source']['userId']);
 
        // if user not registered
        if(!$this->user) $this->followCallback($event);
        else {
          // respond event
          if($event['type'] == 'message'){
            if(method_exists($this, $event['message']['type'].'Message')){
              $this->{$event['message']['type'].'Message'}($event);
            }
          } else {
            if(method_exists($this, $event['type'].'Callback')){
              $this->{$event['type'].'Callback'}($event);
            }
          }
        }
 
      } // end of foreach
    }
    // debuging data
    file_put_contents('php://stderr', 'Body: '.$body);
  } // end of index.php


 //application/controller/Webhook.php
 private function followCallback($event)
 {
   $res = $this->bot->getProfile($event['source']['userId']);
   if ($res->isSucceeded())
   {
     $profile = $res->getJSONDecodedBody();

     // create welcome message
     $message  = "Assalamualaikum Wr.Wb, " . $profile['displayName'] . "!\n";
     $message  = "Jobot merupakan chatbot line yang membantu anda mempersiapkan diri menghadapi Ujian Nasional dan Ujian SBMPTN ";
     $message .= "Silakan kirim pesan \"ayok\" untuk memulai latihan.";
     $textMessageBuilder = new TextMessageBuilder($message);

     // create sticker message
     $stickerMessageBuilder = new StickerMessageBuilder(1, 3);

     // merge all message
     $multiMessageBuilder = new MultiMessageBuilder();
     $multiMessageBuilder->add($textMessageBuilder);
     $multiMessageBuilder->add($stickerMessageBuilder);

     // send reply message
     $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);

     // save user data
     $this->tebakkode_m->saveUser($profile);
   }
 }

 private function textMessage($event)
 {
     // dipisahkan antara pesan text dan pesan postback
     if(strtolower($event['postback']['data'] == 'un biologi'))
     {
         
     }




   $userMessage = $event['message']['text']; // mengambil pesan text dari event
   if($this->user['number'] == 0) // user belum mengerjakan kuis
   {
     if(strtolower($userMessage) == 'ayok')
     {
       // reset score
       $this->tebakkode_m->setScore($this->user['user_id'], 0);
       // update number progress
       $this->tebakkode_m->setUserProgress($this->user['user_id'], 1);
       // send question no.1
       $this->sendQuestion($event['replyToken'], 1);
    }
    
    elseif(strtolower($userMessage) == 'cuk'){
      $flexTemplate = file_get_contents(APPPATH.'/controllers/flex_message.json'); // load template flex message
      $js_dcd = json_decode($flexTemplate);

      $this->$httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
          'replyToken' => $event['replyToken'],
          'messages'   => [
              [
                  'type'     => 'flex',
                  'altText'  => 'Semangat menggapai mimpi !',
                  'contents' => $js_dcd 
              ]
          ],
      ]);
    } 
    else {
      // create sticker message
      $stickerMessageBuilder = new StickerMessageBuilder(1, 106);

      // create text message
      $message = 'Silakan kirim pesan "ayok" untuk memulai kuis.';
      $textMessageBuilder = new TextMessageBuilder($message);

      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($stickerMessageBuilder);
      $multiMessageBuilder->add($textMessageBuilder);

      // send message
      $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder); 
     }

   // if user already begin test
   } else {
     $this->checkAnswer($userMessage, $event['replyToken']);
   }
 }

 


 private function stickerMessage($event)
 {
   // create sticker message
   $stickerMessageBuilder = new StickerMessageBuilder(1, 106);

   // create text message
   $message = 'Silakan kirim pesan "ayok" untuk memulai kuis.';
   $textMessageBuilder = new TextMessageBuilder($message);

   // merge all message
   $multiMessageBuilder = new MultiMessageBuilder();
   $multiMessageBuilder->add($stickerMessageBuilder);
   $multiMessageBuilder->add($textMessageBuilder);

   // send message
   $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
 }


 public function sendQuestion($replyToken, $questionNum=1)
 {
   // get question from database
   $question = $this->tebakkode_m->getQuestion($questionNum);

   // prepare answer options
   for($opsi = "a"; $opsi <= "d"; $opsi++) {
       if(!empty($question['option_'.$opsi]))
           $options[] = new MessageTemplateActionBuilder($question['option_'.$opsi], $question['option_'.$opsi]);
   }

   // prepare button template
   $buttonTemplate = new ButtonTemplateBuilder($question['number']."/10", $question['text'], $question['image'], $options);

   // build message
   $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

   // send message
   $response = $this->bot->replyMessage($replyToken, $messageBuilder);
 }


 private function checkAnswer($message, $replyToken)
 {
   // if answer is true, increment score
   if($this->tebakkode_m->isAnswerEqual($this->user['number'], $message)){
     $this->user['score']++;
     $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);
     $message  = " Benar !";
     $textMessageBuilder = new TextMessageBuilder($message);
     $this->bot->replyMessage($event['replyToken'], $textMessageBuilder); // kirim pesan apabila jawaban betul
   }

   if($this->user['number'] < 10)
   {
     // update number progress
    $this->tebakkode_m->setUserProgress($this->user['user_id'], $this->user['number'] + 1);

     // send next question
     $this->sendQuestion($replyToken, $this->user['number'] + 1);
   }
   else {
     // create user score message
     $message = 'Skormu '. $this->user['score'];
     $textMessageBuilder1 = new TextMessageBuilder($message);

     // create sticker message
     $stickerId = ($this->user['score'] < 8) ? 100 : 114;
     $stickerMessageBuilder = new StickerMessageBuilder(1, $stickerId);

     // create play again message
     $message = ($this->user['score'] < 8) ?
     'Jangan Nyerah !!! Ketik "ayok" untuk berlatih lagi!':
     'Great! Mantap bro! Ketik "ayok" untuk berlatih lagi!';
     $textMessageBuilder2 = new TextMessageBuilder($message);

     // merge all message
     $multiMessageBuilder = new MultiMessageBuilder();
     $multiMessageBuilder->add($textMessageBuilder1);
     $multiMessageBuilder->add($stickerMessageBuilder);
     $multiMessageBuilder->add($textMessageBuilder2);

     // send reply message
     $this->bot->replyMessage($replyToken, $multiMessageBuilder);
     $this->tebakkode_m->setUserProgress($this->user['user_id'], 0);
   }
 }

}
