# Ebay-Api-OAuth-PHP
Ebay Api Get User Token



Requirements 
  - Hkonnet\LaravelEbay\Ebay
  - DTS\eBaySDK



Here is the steps to get access to list of ebay account 



1. You need to create developer account in ebay. https://developer.ebay.com
2. Create a sandbox and production application 
  a. you will have to store the addID and CertID and RuleName in your ENV file
  b. don't forget to create under your application (Get a Token from eBay via Your Application)
  c. add a redirect url for Your auth accepted URL
    1. the url redirect will be a get method to receive the code of the user authorize
    
3. getUserCode() once it's called it should take the user to login of ebay followed by Grant Application Access. Once the user accept, it will take it back to the redirect url you assign to it in the application. From there
4. With the authorization code that you get from ebay you call the function getEbayUserToken(), of course you need to pass the code somehow let say from the database that you stored earlier in the first function getUserCode. 
  a. From that request ebay will get you a response type of json with the User Token. 
  
5. Last just call the function import 
  a. by passing the token code given by ebay 
  b. here is your active listing 
  
  
  
Charbel Mhanna 
