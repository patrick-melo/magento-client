Simple client to call the Magento 2 API.

    require_once '/Users/patrickmelo/eclipse-workspace/magento-client/src/Magento/Client.php';
    // REPLACE WITH YOUR ACTUAL DATA OBTAINED WHILE CREATING NEW INTEGRATION
    $client = new Magento\Client([
        'origin'              => 'http://localhost',
        'consumer_key'        => 'htj8ze6ntr0mz1s4hjxrqeicia8rxgt4',
        'consumer_secret'     => 'djjzdwfgbbr7ganlkv01qr6p3l7ptvfe',
        'access_token'        => '60o0mfrvqnjvin7tjuqsv37arijrqe9e',
        'access_token_secret' => 'caq9wfdx99zaygwgbhw91i9imj89p4zb',
    ]);
    var_dump($client->getProducts(null, 10, 1));