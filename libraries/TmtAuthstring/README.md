# TMT Authstring
A helper class for creating valid authstrings and validating transaction hashes for use with the [Trust My Travel Payment Modal](https://demo.trustmytravel.com/modal/).

## Create Authstring
To create a valid authstring for use with the Payment Modal you will need to pass the following values to the `getAuthstring` method in the `TmtAuthstring\Create` class:
* channel_id: ID of the channel the transaction is in
* channel_currency: currency of the channel the transaction is in
* booking_total: total of the booking as a cent value in the currency of the channel the transaction is in
* channel_secret: secret key of the channel the transaction is in

Please refer to the Payment Modal documentation for detail on how to obtain these values.

### Example
```
require 'TmtAuthstring/Create.php';

$tmtAuthstring     = new \TmtAuthstring\Create();
$channel_id        = 23;
$channel_currency  = 'USD';
$booking_total     = 1000;
$channel_secret    = '$2y$10$ABCDEFEGHIJKLMNOPQRSTUVWXYZ';
$final_auth_string = $tmtAuthstring->getAuthstring($channel_id, $channel_currency, $booking_total, $channel_secret);
```

The `$final_auth_string` would then be included in a hidden input, or included in a data object, depending on the Payment Modal implementation you are using.

## Create Authstring with Additional Fields
Should you wish to ensure that other values are not tampered with, you can include them in the hashed and salted authstring by passing them in an array of additional values. Please note that these fields must be valid payment modal fields according to the implementation in use.

### Example
```
require 'TmtAuthstring/Create.php';

$tmtAuthstring     = new \TmtAuthstring\Create();
$channel_id        = 23;
$channel_currency  = 'USD';
$booking_total     = 1000;
$channel_secret    = '$2y$10$ABCDEFEGHIJKLMNOPQRSTUVWXYZ';
$additional_fields = [
    'reference'     => 'SOMEBOOKINGREFERENCE',
    'allocations    => [
        'channels'   => 24,
        'currencies' => 'GBP,
        'operator'   => 'flat',
        'total'      => 500
    ],
];
$final_auth_string = $tmtAuthstring->getAuthstring($channel_id, $channel_currency, $booking_total, $channel_secret, $additional_fields);
```

## Validate Transaction Hash
To validate the hash field in a transaction response, you will need to instantiate the `TmtAuthstring\Validate` method with the following values:
* `id` field contained in the transaction response
* `status` field contained in the transaction response
* `total`  field contained in the transaction response (nb this could differ from the total value passed when instantiating the modal
* channel_secret Secret key of the channel the transaction is in

You can then validate the value in the `hash` field of the transaction response using the `isValidHash` method.

### Example
```
require 'includes/TmtAuthstring/Validate.php';

$tmtAuthstring = new \TmtAuthstring\Validate(231022, 'complete', 1000, '$2y$10$ABCDEFEGHIJKLMNOPQRSTUVWXYZ');

if ($tmtAuthstring->isValidHash('14e2abcdefgh26b9fce29a0cb56a23afee3b98398ce04567ad378f32dc046b)) {
    // valid hash.
    if ($status === 'complete') {
        // perform successful transaction processes.
    } else {
        // perform failed transaction processes.
    }
}
```