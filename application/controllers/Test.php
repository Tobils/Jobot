<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends CI_Controller {
	public function index()
	{
		$flexTemplate = file_get_contents(APPPATH.'/controllers/flex_message.json'); // load template flex message

        print_r($flexTemplate);
        $js_dcd = json_decode($flexTemplate);
        print_r($js_dcd);
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
                            print_r($result);

	}
}
