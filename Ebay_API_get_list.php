<?php

namespace App\Http\Controllers;
use DTS\eBaySDK\OAuth\Services\OAuthService;
use DTS\eBaySDK\OAuth\Types\GetUserTokenRestRequest;
use DTS\eBaySDK\Trading\Services\TradingService;
use DTS\eBaySDK\Trading\Types\CustomSecurityHeaderType;
use DTS\eBaySDK\Trading\Types\GetMyeBaySellingRequestType;
use Hkonnet\LaravelEbay\Ebay;
use Hkonnet\LaravelEbay\EbayServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use DTS\eBaySDK\Constants;
use DTS\eBaySDK\Trading\Enums;
use DTS\eBaySDK\Trading\Types;

class ebayController extends Controller
{


    public function getEbayUserToken()
    {
        $config = Config::get('ebay.production.credentials');
        $authorizationEncoded = 'Basic ' . base64_encode($config['appId'] . ':' . $config['certId']);
        $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";
        $client = new \GuzzleHttp\Client(['headers' =>
            ['Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => $authorizationEncoded]]);

        $response = $client->request('POST', $endpoint, [ 'form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $_GET['code'],
            'redirect_uri'=> '<RULE NAME OF APP>',
        ]]);

        $result = json_decode($response->getBody(), true);

    }


    public function getUserCode(Request $request)
    {
      
        $service = new Ebay();
        $authToken = $service->getAuthToken();
        return Redirect::intended($authToken);
    }


public function updateToken(Request $request)
    {
        $request->validate(
            [
                'id' => 'required',
            ]);


        $account = DB::table('db_name')
            ->where('status', 'A')
            ->where('id', $request->input('id'))->get();


        $config = Config::get('ebay.production.credentials');
        $authorizationEncoded = 'Basic ' . base64_encode($config['appId'] . ':' . $config['certId']);
        $endpoint = "https://api.ebay.com/identity/v1/oauth2/token";
        $client = new \GuzzleHttp\Client(['headers' =>
            ['Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => $authorizationEncoded]]);

        $response = $client->request('POST', $endpoint, [ 'form_params' => [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account[0]->refresh_token,

        ]]);

        $result = json_decode($response->getBody(), true);

        DB::table('db_name')
            ->where('id', $account[0]->id)
            ->update(['auth_code' => $account[0]->auth_code,
                'token_code' => $result['access_token'],
                'expires_in' => $result['expires_in']]);

        $notification = array(
            'message' => 'Token refreshed successfully!',
            'alert-type' => 'success'
        );


        return back()->with($notification)->with('success', 'Token refreshed successfully !');

    }
    
    
     private function getImagesProduct($itemId, $token)
    {

        $request = '<?xml version="1.0" encoding="utf-8"?>
                    <GetSingleItemRequest xmlns="urn:ebay:apis:eBLBaseComponents" >
                    <ItemID>' . $itemId . '</ItemID>
                    <IncludeSelector>Details</IncludeSelector>
                    </GetSingleItemRequest>';

        $callName = 'GetSingleItem';
        $compatibilityLevel = 863;
        $endpoint = "http://open.api.ebay.com/shopping";
        $headers[] = "X-EBAY-API-CALL-NAME: $callName";
        $headers[] = "X-EBAY-API-IAF-TOKEN: " . $token;
        $headers[] = "X-EBAY-API-VERSION: $compatibilityLevel";
        $headers[] = "X-EBAY-API-REQUEST-ENCODING: XML";
        $headers[] = "X-EBAY-API-RESPONSE-ENCODING: XML";
        $headers[] = "X-EBAY-API-SITE-ID: 0";
        $headers[] = "Content-Type: text/xml";

        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);

        $response2 = curl_exec($curl);
        $data = simplexml_load_string($response2);


        return $data;
    }



    public function importList()
    {

        $config = Config::get('ebay.production.credentials');

        $service = new TradingService([
            'credentials' => $config,
            'siteId'      => Constants\SiteIds::US
        ]);

        $ebay_service = new EbayServices();
        $service = $ebay_service->createTrading();

        /**
         * Create the request object.
         */
        $request = new Types\GetMyeBaySellingRequestType();

        /**
         * An user token is required when using the Trading service.
         */
        $request->RequesterCredentials = new Types\CustomSecurityHeaderType();
        $request->RequesterCredentials->eBayAuthToken = <WHERE YOU STORE THE USER TOKEN FROM GETUSERTOKEN FUNCTION>;



        $request = new Types\GetMyeBaySellingRequestType();
        $request->ActiveList = new Types\ItemListCustomizationType();
        $request->ActiveList->Include = true;
        $request->ActiveList->Pagination = new Types\PaginationType();
        $request->ActiveList->Pagination->EntriesPerPage = 10;
        $request->ActiveList->Sort = Enums\ItemSortTypeCodeType::C_CURRENT_PRICE_DESCENDING;
        $pageNum = 1;

        do {
            $request->ActiveList->Pagination->PageNumber = $pageNum;

            /**
             * Send the request.
             */
            $response = $service->getMyeBaySelling($request);




            /**
             * Output the result of calling the service operation.
             */
            echo "==================\nResults for page $pageNum\n==================\n";

            if (isset($response->Errors)) {
                foreach ($response->Errors as $error) {
                    printf(
                        "%s: %s\n%s\n\n",
                        $error->SeverityCode === Enums\SeverityCodeType::C_ERROR ? 'Error' : 'Warning',
                        $error->ShortMessage,
                        $error->LongMessage
                    );
                }
            }

            if ($response->Ack !== 'Failure' && isset($response->ActiveList)) {
                foreach ($response->ActiveList->ItemArray->Item as $item) {
                    printf(
                        "(%s) %s: %s %.2f\n",
                        $item->ItemID,
                        $item->Title,
                        $item->SellingStatus->CurrentPrice->currencyID,
                        $item->SellingStatus->CurrentPrice->value
                    );
                }
            }

            $pageNum += 1;

        } while (isset($response->ActiveList) && $pageNum <= $response->ActiveList->PaginationResult->TotalNumberOfPages);
    }


}
?>
