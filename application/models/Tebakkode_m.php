<?php defined('BASEPATH') OR exit('No direct script access allowed');
// added
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;

class Tebakkode_m extends CI_Model {

  private $bot; // added

  function __construct(){
    parent::__construct();
    $this->load->database();

    // create bot object (added)
    $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $bot  = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
  }

  // Events Log
  function log_events($signature, $body)
  {
    $this->db->set('signature', $signature)
    ->set('events', $body)
    ->insert('eventlog');
    return $this->db->insert_id();
  }

 /// Users
 function getUser($userId)
 {
   $data = $this->db->where('user_id', $userId)->get('users')->row_array();
   if(count($data) > 0) return $data;
   return false;
 }

 function saveUser($profile)
 {
   $this->db->set('user_id', $profile['userId'])
     ->set('display_name', $profile['displayName'])
     ->insert('users');

   return $this->db->insert_id();
 }

  // Question
  function getQuestion($questionNum)
  {
    $data = $this->db->where('number', $questionNum)
      ->get('questions')
      ->row_array();

    if(count($data)>0) return $data;
    return false;
  }

  function isAnswerEqual($number, $answer)
  {
    $this->db->where('number', $number)
      ->where('answer', $answer);

    if(count($this->db->get('questions')->row()) > 0)
      return true;

    return false;
  }

  function setUserProgress($user_id, $newNumber)
  {
    $this->db->set('number', $newNumber)
      ->where('user_id', $user_id)
      ->update('users');

    return $this->db->affected_rows();
  }

  function setScore($user_id, $score)
  {
    $this->db->set('score', $score)
      ->where('user_id', $user_id)
      ->update('users');

    return $this->db->affected_rows();
  }

  function view_Flex()
  {
    $flexTemplate = file_get_contents(APPPATH.'/models/flex_message.json'); // load template flex message
    $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
      'replyToken' => $event['replyToken'],
      'messages'   => [
          [
              'type'     => 'flex',
              'altText'  => 'Semangat menggapai mimpi !',
              'contents' => json_decode($flexTemplate)
          ]
      ],
  ]);
  return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
  }
}
