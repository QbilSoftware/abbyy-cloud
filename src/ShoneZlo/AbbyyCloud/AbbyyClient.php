<?php

namespace ShoneZlo\AbbyyCloud;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

class AbbyyClient
{

    /**
     * @var Client
     */
    private $api;

    /**
     * @param string $appName
     * @param string $password
     */
    public function __construct($appName, $password)
    {
        $this->api = new Client([
            'base_uri' => 'https://cloud.ocrsdk.com/',
            'auth' => [$appName, $password]
        ]);
    }

    /**
     * @param string $inputFilePath
     * @return string
     * @throws AbbyyException
     */
    public function performOcr($inputFilePath, $language = 'english', $imageSource = 'scanner', $profile = 'barcodeRecognition', $exportFormat = 'xml')
    {
        $task = $this->submitImage($inputFilePath, $language, $imageSource, $profile, $exportFormat);

        while (true) {
            sleep($task->getEstimatedProcessingTime());
            $task = $this->checkTask($task);

            if ($task->getResultUrl()) {
                break;
            }
        }

        try {
            $resp = (new Client())->get($task->getResultUrl());
            return $resp->getBody()->getContents();
        } catch (BadResponseException $ex) {
            throw AbbyyException::parseXml($ex->getResponse()->getBody()->getContents(), $ex->getCode());
        }
    }

    /**
     * @param string $inputFilePath
     * @param string $language
     * @param $imageSource
     * @param $profile
     * @param $exportFormat
     * @return AbbyyTask
     * @throws AbbyyException
     */
    public function submitImage($inputFilePath, $language = 'english', $imageSource, $profile, $exportFormat)
    {
        $options = [
            'query' => [
                'profile' => $profile,
                'imageSource' => $imageSource,
                'exportFormat' => $exportFormat,
                'language' => $language
            ],
            'body' => fopen($inputFilePath, 'r')
        ];

        try {
            $resp = $this->api->post('processImage', $options);
            return AbbyyTask::parseXml($resp->getBody()->getContents());
        } catch (BadResponseException $ex) {
            throw AbbyyException::parseXml($ex->getResponse()->getBody()->getContents(), $ex->getCode());
        }
    }

    /**
     * @param AbbyyTask $task
     * @return AbbyyTask
     * @throws AbbyyException
     */
    public function checkTask(AbbyyTask $task)
    {
        $options = [
            'query' => ['taskid' => $task->getId()]
        ];

        try {
            $resp = $this->api->get('/getTaskStatus', $options);
            return AbbyyTask::parseXml($resp->getBody()->getContents());
        } catch (BadResponseException $ex) {
            throw AbbyyException::parseXml($ex->getResponse()->getBody()->getContents(), $ex->getCode());
        }
    }

}
