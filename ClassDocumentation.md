# Usage #
```
require_once 'skipjack.php';
sj = new Skipjack(); 
$sj->setDeveloper(true); // use the development server address, remove this line to use the live server

$sj->addFields(array(
        'OrderNumber' => '5', 
        'ItemNumber' => 'i5', 
        'ItemDescription' => 'Test Item',
        'ItemCost' => '5.50',
        'Quantity' => '1',
        'Taxable' => '0',
        'AccountNumber' => '4445999922225', 
        'Month' => '12',
        'Year' => '2010',
        'TransactionAmount' => '200.00'
    ));

if($sj->process() && $sj->isApproved()) { 
    echo "Transaction approved!";
} else {
    echo "Transaction declined!\n";
    echo $sj->getErrors();
}
```