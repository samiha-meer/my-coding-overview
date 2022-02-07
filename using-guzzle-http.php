<?php

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;

class JRServiceV2 {

    private $ws_client;
    public function __construct() {

        $this->ws_client = new \GuzzleHttp\Client(
            [
                'base_uri' =>  $GLOBALS['jr_rest_api'],'cookies' => true,
            ] 
        );
    }

    public function initiateProcess($data){

        try {
            //authenticate user
            $response = $this->ws_client->post('application/sessions', [
                'body'    => json_encode($GLOBALS['jruser_credentials']),
                'headers' => [
                    'content-type' => 'application/json',
                ],
            ]);
            // Determine session ID
            $sessionData = json_decode($response->getBody(), true);
            $sessionId = $sessionData['sessions'][0]['sessionId'];
            if(!empty($sessionId)) {
                $response = $this->ws_client->request('POST', 'application/incidents/'.$data['processName'],
                    [
                        'multipart' => $data['processData'],
                    ]
                );
                
                $body = $response->getBody();
                $decodedBody = json_decode($body->getContents(), true);
                $incidentData = $decodedBody['incidents'][0];
                $_SESSION['workflowId'] = $incidentData['workflowId'];

                return [
                    'status' => 'success',
                    'message' => "Process initiated successfully.",
                ];
            }
        
        } catch (Exception $e) {

            $GLOBALS['container']->logger->error($e->getMessage());
            return [
                'status' => 'failure',
                'message' => "<b>Error!</b> Process initialization failed,please check log for details.",
            ];
        }
    }

    public function sendStartStep($data) {
        try{

            $input = [
                'processName' => $data['processName'],
                'processVersion' => $data['version'],
                'stepNo' =>  $data['step'],
                'action' => 'send',
                'workflowId' =>  $_SESSION['workflowId'],
                'dialogType' => 'desktop',
                "dialog"=> [
                    "fields" => $data['dialogFields']
                ]
            ];
            $response = $this->ws_client->request('PUT','application/steps/' . $input['workflowId'],
                [
                    'json' => $input
                ]
            );

            $response = $this->ws_client->delete('application/sessions');
            unset($_SESSION['workflowId']);
            
            return [
                'status' => 'success',
                'message' => LBL_SUCCESS_SAVED,
            ];
        
        } catch (Exception $e) {

            $GLOBALS['container']->logger->error($e->getMessage());
            return [
                'status' => 'failure',
                'message' => "<b>Error!</b> Something went wrong,please check log for details.",
            ];
        }

    }
}
