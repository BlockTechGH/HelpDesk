<?php
namespace App\Controller\Component;

use Bitrix24\Exceptions\Bitrix24TokenIsExpiredException;
use Monolog\Logger;
use Cake\Routing\Router;
use Cake\Core\Configure;
use Cake\Controller\Component;
use Monolog\Handler\StreamHandler;
use Bitrix24\Exceptions\Bitrix24ApiException;
use Bitrix24\Exceptions\Bitrix24SecurityException;

class Bx24Component extends Component
{

    private $controller = null;
    private $BitrixTokens = null;
    private $obBx24App = null;
    public $bx24Logger = null;

    private $appBaseUrl = null;

    public function initialize(array $config = []): void
    {
        parent::initialize($config);
        $this->controller = $this->_registry->getController();
        $this->BitrixTokens = $this->controller
            ->getTableLocator()
            ->get('BitrixTokens');

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'bx24app.log';
        $this->bx24Logger = new Logger('BX24');
        $this->bx24Logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $this->obBx24App = new \Bitrix24\Bitrix24(false, $this->bx24Logger);
        $this->obBx24App->setOnExpiredToken(function() {
            $this->bx24Logger->debug("Access token is expired. Refresh tokens");
            $this->refreshTokens();
            return true;
        });

        $this->obBx24App->setApplicationScope(["crm"]);
        $this->obBx24App->setDomain($this->controller->domain);
        $this->obBx24App->setMemberId($this->controller->memberId);
        $this->obBx24App->setAccessToken($this->controller->authId);
        $this->obBx24App->setRefreshToken($this->controller->refreshId);

        $this->obBx24App->setApplicationId(Configure::read('AppConfig.client_id'));
        $this->obBx24App->setApplicationSecret(Configure::read('AppConfig.client_secret'));
        $appBaseURL = Configure::read('AppConfig.appBaseUrl');
        $this->obBx24App->setRedirectUri($appBaseURL);

        if($this->obBx24App->isAccessTokenExpire())
        {
            $this->bx24Logger->debug("Access token is expired. Refresh tokens");
            $this->refreshTokens();
        }
    }

    #
    #section Task 3. Script for installation application
    #

    public function getInstalledData(): array
    {
        $arData = [
            'activityTypeList' => [],
            'eventList' => []
        ];

        $this->obBx24App->addBatchCall('crm.activity.type.list', [], function($result) use (&$arData)
        {
            if($result['result'])
            {
                $arData['activityTypeList'] = $result['result'];
            }
        });

        $this->obBx24App->addBatchCall('event.get', [], function($result) use (&$arData)
        {
            if($result['result'])
            {
                $arData['eventList'] = $result['result'];
            }
        });

        $this->obBx24App->processBatchCalls();

        $this->bx24Logger->debug('getInstalledData - data', [
            '$arData' => $arData,
        ]);

        return $arData;
    }

    public function installApplicationData($arInstalledData)
    {
        $wwwRoot = Configure::read('App.wwwRoot');
        // "https://icons8.com/icon/6837/служба-поддержки"
        $iconFile = "helpdesk.png";
        $iconPath = $wwwRoot . "img/" . $iconFile;
        $activityDef = static::getActivityTypeAndName();


        // add activity type
        if (!count($arInstalledData['activityTypeList'])) {
            $arActivityTypeParams['fields'] = array_merge(
                [
                    'ICON_FILE' => [
                        $iconFile,
                        base64_encode(file_get_contents($iconPath))
                    ],
                ],
                $activityDef
            );

            $this->obBx24App->addBatchCall('crm.activity.type.add', $arActivityTypeParams, function ($result) use ($arActivityTypeParams) {
                $this->bx24Logger->debug('installApplicationData - crm.activity.type.add', [
                    'arParams' => $arActivityTypeParams,
                    'result' => $result
                ]);
            });
        }


        // add placements in crm card
        $arNeedPlacements = [
            'CRM_LEAD_DETAIL_ACTIVITY',
            'CRM_DEAL_DETAIL_ACTIVITY',
            'CRM_CONTACT_DETAIL_ACTIVITY',
            'CRM_COMPANY_DETAIL_ACTIVITY'
        ];

        foreach ($arInstalledData['placementList'] as $placement) {
            // unbind old placements first
            if (in_array($placement['placement'], $arNeedPlacements)) {
                $arUnbindParams = [
                    'PLACEMENT' => $placement['placement'],
                    'HANDLER' => $placement['handler']
                ];

                $this->obBx24App->addBatchCall('placement.unbind', $arUnbindParams, function ($result) use ($arUnbindParams) {
                    $this->bx24Logger->debug('installApplicationData - placement.unbind', [
                        'arParams' => $arUnbindParams,
                        'result' => $result
                    ]);
                });
            }
        }

        foreach ($arNeedPlacements as $placement) {
            $arBindParams = [
                'PLACEMENT' => $placement,
                'HANDLER' => $this->getRouteUrl('crm_interface'),
                'LANG_ALL' => [
                    'en' => [
                        'TITLE' => $activityDef['NAME']
                    ]
                ]
            ];

            $this->obBx24App->addBatchCall('placement.bind', $arBindParams, function ($result) use ($arBindParams) {
                $this->bx24Logger->debug('installApplicationData - placement.bind', [
                    'arParams' => $arBindParams,
                    'result' => $result
                ]);
            });
        }


        // Bind/unbind on OnCrmActivityAdd, OnCrmActivityDelete, OnCrmActivityDelete
        $arNeedEvents = [
            'ONCRMACTIVITYADD' => 'crm_activity_handler',
            'ONCRMACTIVITYUPDATE' => 'crm_activity_handler',
            'ONCRMACTIVITYDELETE' => 'crm_activity_handler',
        ];

        foreach($arInstalledData['eventList'] as $event)
        {
            if(isset($arNeedEvents[$event['event']]))
            {
                $arUnbindEventParams = [
                    'event' => $event['event'],
                    'handler' => $event['handler']
                ];

                $this->obBx24App->addBatchCall('event.unbind', $arUnbindEventParams, function($result) use ($arUnbindEventParams)
                {
                    $this->bx24Logger->debug('installApplicationData - event.unbind', [
                        'arParams' => $arUnbindEventParams,
                        'result' => $result
                    ]);
                });
            }
        }

        foreach($arNeedEvents as $event => $routeName)
        {
            $arBindEventParams = [
                'event' => $event,
                'handler' => $this->getRouteUrl($routeName),
            ];
            $this->obBx24App->addBatchCall('event.bind', $arBindEventParams, function($result) use ($arBindEventParams)
            {
                $this->bx24Logger->debug('installApplicationData - event.bind', [
                    'arParams' => $arBindEventParams,
                    'result' => $result
                ]);
            });
        }

        $this->obBx24App->processBatchCalls();
    }

    public function removeActivityTypes($arInstalledData)
    {
        foreach($arInstalledData['activityTypeList'] as $activityType)
        {
            $arParams = [
                'TYPE_ID' => $activityType['TYPE_ID']
            ];

            $this->obBx24App->addBatchCall('crm.activity.type.delete', $arParams, function($result)
            {
            });
        }

        $this->obBx24App->processBatchCalls();
    }

    #
    #endsection
    #

    private static function getActivityTypeAndName()
    {
        $postfix = Configure::read('AppConfig.itemsPostfix');
        return [
            'TYPE_ID' => 'TICKET' . (($postfix) ? "_" . strtoupper($postfix) : ''),
            'NAME' => __('Ticket') . (($postfix) ? " " . $postfix : ''),
        ];
    }

    private function getRouteUrl(string $routeName)
    {
        if (!$this->appBaseUrl) {
            $this->appBaseUrl = Configure::read('AppConfig.appBaseUrl');
        }
        $routeProps = ['_name' => $routeName ];
        return ($this->appBaseUrl)
            ? $this->appBaseUrl . Router::url($routeProps, false)
            : Router::url($routeProps, true);
    }

    private function refreshTokens()
    {
        $oldAccessToken = $this->obBx24App->getAccessToken();
        $oldRefreshToken = $this->obBx24App->getRefreshToken();
        $tokensRefreshResult = $this->obBx24App->getNewAccessToken();
        $this->bx24Logger->debug('refreshTokens - getNewAccessToken - result', [
            'tokensRefreshResult' => $tokensRefreshResult
        ]);
        $this->obBx24App->setAccessToken($tokensRefreshResult["access_token"]);
        $this->obBx24App->setRefreshToken($tokensRefreshResult["refresh_token"]);

        $this->BitrixTokens->writeAppTokens(
            $this->obBx24App->getMemberId(),
            $this->obBx24App->getDomain(),
            $this->obBx24App->getAccessToken(),
            $this->obBx24App->getRefreshToken(),
            $tokensRefreshResult["expires_in"]
        );

        $this->bx24Logger->debug("refreshTokens - status", [
            "Access token refreshed" => $oldAccessToken != $tokensRefreshResult["access_token"],
            "Refresh token refreshed" => $oldRefreshToken != $tokensRefreshResult["refresh_token"],
        ]);
    }
}
