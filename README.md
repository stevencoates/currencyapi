Currency Conversion API
====
Note
----
This currency conversion API was written as a piece of university coursework intended to teach how to make RESTful APIs.
As a result of this, not all of the features present are exactly how they may otherwise be implemented, and changes are likely to be made regarding the following of these aspects:
- The GET request currently has a different response format to the POST, PUT and DELETE requests.
- Different sets of error messages are present for the GET request, compared to the POST, PUT and DELETE requests, with the same purposes.
- All request types are handled through GET headers sent to different files.

Using the API
----
This API makes use of [Open Exchange Rates](https://openexchangerates.org/). Inside of configuration.xml there is a field for an Open Exchange Rates key, which has been ommitted in this repo, and will need to be provided to make use of this API.

__Sending Requests__
To make a request to this API, a GET request must be sent containing a number of the following parameters:
- "_from_", a three letter ISO currency code to be converted from, used by the GET request.
- "_to_", a three letter ISO currency code to be converted to, used by the GET request.
- "_amnt_", a decimal value to be converted, used by the GET request.
- "_format_", either XML or JSON, specifying the format that the response should be given in, used by the GET request.
- "_code_", a three letter ISO currency code for an action to be carried out on, used by the PUT, POST and DELETE requests.
- "_rate_", a decimal value to set the exchange rate of a currency to, used by the POST request.

__Interacting with the PHP Class__
To make use of the class that this API has been created around, within another PHP script, you must initiate an instance of the class "currencyapi", and call any of the given request types as a method, with a set of parameters passed in. The parameters to be assed in are those mentioned above, but instead of being provided through a GET request, they must be passed in to the method as an associative array.
