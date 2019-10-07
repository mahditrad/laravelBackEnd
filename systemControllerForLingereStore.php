<?php

namespace App\Http\Controllers;

use App\Product;
use App\User;
use App\StockProduct;
use App\Transaction;
use App\SoldItems;
use App\ReturnProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class systemController extends Controller
{
    //
    public function addProduct(request $request)
    {
        $product = $request->input('product');
        $product = json_decode($product,true);
        $productCheckReference = Product::where('reference',$product['reference'])->get();
        
        if(count($productCheckReference) == 0)
        {
            $data = Product::create($product);
            return $product ;
        }
         return "Already created";
    }
    public function addStock(request $request)
    {
        $stock = $request->input('stock');
        $stock = json_decode($stock,true);
        $data = StockProduct::where ([
                                'productId' => $stock['productId'],
                                'sizeeu' => $stock['sizeeu'] ,
                                'cup' => $stock['cup'] ,
                                'color' => $stock['color']

        ])->get();
        $stockCheckBarcode = StockProduct::where('barcode',$stock['barcode'])->get();
        if(count($data) == 0 && count($stockCheckBarcode) == 0)
        {
            $stock['sizefr'] = $stock['sizeeu']+15 ;
            $addToStock = StockProduct::create($stock);
            return 'done' ;
        }
        return 'exists';
    }
    public function getProducts()
    {
        $products = Product::get();
        return $products ;
    }
    public function getProductsAndStock(request $request)
    {
        $referenceId = $request->input('refId');
        $data = Product::where('products.id',$referenceId)
                ->join('stockproducts','stockproducts.productId','=','products.id')
                ->whereNull('stockproducts.deleted_at')
                ->whereNull('products.deleted_at')
                ->get();
        return $data;
    }
    public function updateQuantity(request $request)
    {
        $id = $request->input('id');
        $addedQuantity = $request->input('addedQuantity');
        if($addedQuantity <0)
        {
            return 'lessThanZero';
        }
        StockProduct::where('id',$id)
           ->increment('quantity', $addedQuantity);
        return 'done';
    }
    public function getAllStocks()
    {
        $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
                        ->whereNull('stockproducts.deleted_at')
                        ->whereNull('products.deleted_at')
                        ->get();
        return $data; 
    }
    public function saveStockEdit(request $request)
    {
        $data = $request->input('stock');
        $data = json_decode($data,true);
        
        $productRef = product::where('reference',$data['reference'])->get(['id']); // id for the ref that user enterted
        if(count($productRef) == 0)  return 'noRef';
       
        $oldStock = StockProduct::where('id',$data['id'])->get(); // old stock data that are not changed yet 
        $data['newProductId'] = $productRef[0]['id'];
        
        if($data['newProductId'] == $data['productId'] && $oldStock[0]['sizeeu'] == $data['sizeeu'] &&
        $oldStock[0]['cup'] == $data['cup'] &&  $oldStock[0]['color'] == $data['color']) // if quantity is changed only
        {
            $checkBarcode =  StockProduct::where('barcode',$data['barcode'])->get();
                if($oldStock[0]['barcode'] != $data['barcode'] && count($checkBarcode) > 0 )
                    return 'barcodeExists'; 
            StockProduct::where('id',$data['id'])
                ->update([
                    'quantity' => $data['quantity'],
                    'barcode'=> $data['barcode']
                ]);
                return 'updated' ;
        }

        if(($data['newProductId'] == $data['productId'] || $data['newProductId'] != $data['productId']) || ($oldStock[0]['sizeeu'] != $data['sizeeu'] || $oldStock[0]['cup'] != $data['cup'] 
            ||  $oldStock[0]['color'] != $data['color'] || $oldStock[0]['barcode'] != $data['barcode'] || $oldStock[0]['barcode'] == $data['barcode'] ))
            {
               
                if($data['newProductId'] == $data['productId'])
                {
                    $check = StockProduct::where('productId',$data['productId'])
                                         ->where('sizeeu',$data['sizeeu'])
                                         ->where('cup',$data['cup'])
                                         ->where('color',$data['color'])
                            ->get();
                    $checkBarcode =  StockProduct::where('barcode',$data['barcode'])->get();
                    if(count($check) > 0 ) return 'productWithSameRefExists'; 
                    if($oldStock[0]['barcode'] != $data['barcode'] && count($checkBarcode) > 0 )
                            return 'barcodeExists'; 
                    else if(count($check) == 0)
                    {
                            StockProduct::where('id',$data['id'])
                                ->update([
                                    'sizeeu' => $data['sizeeu'] ,
                                    'sizefr' => $data['sizeeu']+15 ,
                                    'cup' => $data['cup'] ,
                                    'color' => $data['color'],
                                    'barcode' => $data['barcode']
                                ]);
                        return 'updated';        
                    }              
                }
                else 
                {
                    $check = StockProduct::where('productId',$data['newProductId'])
                                         ->where('sizeeu',$data['sizeeu'])
                                         ->where('cup',$data['cup'])
                                         ->where('color',$data['color'])
                            ->get();
                            //return $check;
                    $checkBarcode =  StockProduct::where('barcode',$data['barcode'])->get();
                    if(count($check) > 0 ) return 'productWithSameRefExists'; 
                    if($oldStock[0]['barcode'] != $data['barcode'] && count($checkBarcode) > 0 )
                         return 'barcodeExists'; 
                    else if(count($check) == 0 )
                    {
                        StockProduct::where('id',$data['id'])
                                ->update([
                                    'productId' => $data['newProductId'],
                                    'sizeeu' => $data['sizeeu'] ,
                                    'sizefr' => $data['sizeeu']+15 ,
                                    'cup' => $data['cup'] ,
                                    'color' => $data['color'],
                                    'barcode' => $data['barcode']
                                ]);
                        return 'updated';    
                    }        
                }
            }
    }

    public function saveProductEdit(request $request)
    {
        $data = $request->input('data');
        $data = json_decode($data,true);

        $oldData = Product::where('id',$data['id'])->get();
        
        if($data['reference'] != $oldData[0]['reference'])
        {
            $oldProduct = Product::where('reference',$data['reference'])->get();
            if(count($oldProduct) > 0)
                return "refExists" ;
        }

            Product::where('id',$data['id'])
            ->update([
            'reference' => $data['reference'],
            'style' => $data['style'] ,
            'description' => $data['description'],
            'itemPrice' => $data['itemPrice'],
            'companyPrice' => $data['companyPrice']
            ]);
           return "updated";
    }

    public function deleteProduct (request $request)
    {
        $id = $request->input("id");
        Product::where('id',$id)->delete();
        StockProduct::where('productId',$id)->delete();
        return "deleted";
    }
    public function deleteStock (request $request)
    {
        $id = $request->input("id");
        StockProduct::where('id',$id)->delete();
        return "deleted";
    }
    public function unlock (request $request)
    {
        $unlock = $request->input("lock");
        $check = User::where('id',1)->where('password',$unlock)->get();
        if(count($check) > 0 )
        {
            return 'Auth';
        }
        else 
        {
            return 'notAuth';
        }
    }
    public function getProductByBarCode (request $request)
    {
        $barcode = $request->input("barcode");
        $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
        ->where('stockproducts.barcode',$barcode)
        ->whereNull('stockproducts.deleted_at')
        ->whereNull('products.deleted_at')
        ->where('stockproducts.quantity','>',0)
        ->get();
        return $data;
        
    }

    public function getReturnProductByBarCode (request $request)
    {
        $barcode = $request->input("barcode");
        $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
        ->where('stockproducts.barcode',$barcode)
        ->whereNull('stockproducts.deleted_at')
        ->whereNull('products.deleted_at')
        ->get();
        return $data;
        
    }
    public function checkout (request $request)
    {
        $soldData = $request->input("enteredProducts");
        $soldData = json_decode($soldData,true);
        $transaction = $request->input("calculations");
        $transaction = json_decode($transaction,true);
        $mytime = Carbon::today()->toDateString();
        $trans = Transaction::where('created_at','like', '%' .$mytime. '%')->max('transactionRef');
        if(count($trans) == 0)
        $transaction['transactionRef'] = 1 ;
        else
        $transaction['transactionRef'] = $trans +1 ;

        $transaction = Transaction::create($transaction);
        for($i=0;$i<count($soldData);$i++)
        {
            $receipt =SoldItems::create(['stockId' => $soldData[$i]['id'],
                                         'transactionId' =>  $transaction['id'],
                                         'companyPrice' => $soldData[$i]['companyPrice'],
                                         'itemPrice' => $soldData[$i]['itemPrice'],
                                         'quantity' => $soldData[$i]['qty'],
                                         'discount' => $soldData[$i]['discount'],
                                         'total' => $soldData[$i]['total']
            ]);
            $decrementQty = StockProduct::where('id',$soldData[$i]['id'])
                            ->decrement('quantity', $soldData[$i]['qty']);
        }
        return $transaction;
        
    }
    public function getInvoiceData (request $request)
    {
        $transactionId = $request->input("transactionId");
        $transactionId = json_decode($transactionId,true);
        $items = SoldItems::where('transactionId',$transactionId)->get();
        return $items ;
    }
    
    public function getHomeInfo()
    {
        $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
                        ->whereNull('stockproducts.deleted_at')
                        ->whereNull('products.deleted_at')
                        ->get();
        $totalStock = 0;
        $itemsCount = 0;
        $orderTotal = 0;
        $totalReturns = 0;
        $transOrderNoRef = 0;
        $totalReturnsNoTransId = 0;
        $totalReturnsNoRef = 0;
        $mytime = Carbon::today()->toDateString();
        //$mytime = Carbon::today()->toDateTimeString();
        $income = Transaction::where('created_at','like', '%' .$mytime. '%')->get();  
        $returns = ReturnProduct::where('returnproduct.created_at','like', '%' .$mytime. '%')
                                ->join('transaction','transaction.id','=','returnproduct.transactionId')
                                ->select('returnproduct.total','transaction.orderTotal','returnproduct.transactionId as transId')
                                ->where('returnproduct.hasRef',0)
                                //->whereNotNull('returnproduct.transactionId')
                                ->get()
                                ->groupBy('transId');
        $returnsNoTransId = ReturnProduct::where('created_at','like', '%' .$mytime. '%')
                                        ->select('returnproduct.total')
                                         ->where('hasRef',0)
                                         ->whereNull('transactionId')
                                         ->get();                      
        //return $returns;
         foreach($returns as $transId => $return)
        {   
            $totalReturnsNoRef =0;
            for($x=0 ; $x<count($return) ; $x++)
            {
                if($x==0)
                     $transOrderNoRef = $return[0]['orderTotal'];
                $totalReturnsNoRef+=$return[$x]['total'];
            }

            if($totalReturnsNoRef - $transOrderNoRef >0){
                $totalReturns+=($totalReturnsNoRef-$transOrderNoRef);
                $orderTotal-=$transOrderNoRef;
            }
           
        }
        //return $totalReturns;                    
        for($i=0;$i<count($data);$i++)
        {
            $totalStock+=$data[$i]['quantity'];
        }   
        for($i=0;$i<count($returnsNoTransId);$i++)
        {
            $totalReturnsNoTransId+=$returnsNoTransId[$i]['total'];
        }   
        for($i=0;$i<count($income);$i++)
        {
            $itemsCount += $income[$i]['itemsCount'] ;
            $orderTotal += $income[$i]['orderTotal'] ;
        }
        //return $totalReturns;
        $stat['totalStock'] = $totalStock;
        $stat['itemsCount'] = $itemsCount - count($returns);
        $stat['orderTotal'] = $orderTotal; 
        $stat['totalReturns'] =  $totalReturns+$totalReturnsNoTransId;
        $final['stat'] = $stat ;
        $final['data'] = $data ;
        return $final; 
    }

    // public function getReturnProduct (request $request)
    // {
    //     $barcode = $request->input("barcode");
    //     $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
    //     ->join('soldItems','stockproducts.id','=','soldItems.stockId')
    //     ->where('stockproducts.barcode',$barcode)
    //     //->whereNull('stockproducts.deleted_at')
    //     //->whereNull('products.deleted_at')
    //     ->get(['stockProducts.id as stockId','products.itemPrice as amount','soldItems.transactionId']);
    //     if(count($data) > 0)
    //     return $data[0] ;
    //     return [] ;
    // }
    public function returnProduct (request $request)
    {
        $product = $request->input("product");
        $product = json_decode($product,true);
        $date=$product['date'];
        $date=str_replace('-','/',$date);  
        $product['date']=date('Y-m-d',strtotime($date));
        $rp = ReturnProduct::create($product);
        StockProduct::where('id',$product['stockId'])
        ->increment('quantity', 1);
        return $rp ;
    }
    public function checkqty (request $request)
    {
        $id = $request->input("id");
        $quantity = $request->input("qty");
        $check= StockProduct::where('id',$id)->get();
        if($check[0]['quantity'] >= $quantity && $quantity > 0 && $quantity != null && $quantity != "")
        return 'valid';
        return 'unvalid' ;
    }
    public function getReportData (request $request)
    {
        $reportName = $request->input("reportName");
        $range = $request->input("range");
        $range=json_decode($range,true);
        
        $from=$range['from'];
        $date=str_replace('-','/',$from);  
        $from=date('Y-m-d',strtotime($from));

        $to=$range['to'];
        $date=str_replace('-','/',$to);  
        $to=date('Y-m-d',strtotime($to."+1 days"));
        //return $from ;
        if($reportName == "missing")
        {
            $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
                        ->where('stockproducts.quantity',0)
                        ->whereNull('stockproducts.deleted_at')
                        ->whereNull('products.deleted_at')
                        ->get();
        }
        if($reportName == "entered")
        {
            $data = Product::join('stockproducts','stockproducts.productId','=','products.id')
                        ->where('stockproducts.created_at','>=',$from)
                        ->where('stockproducts.created_at','<',$to)
                        ->whereNull('stockproducts.deleted_at')
                        ->whereNull('products.deleted_at')
                        ->get();
        }
        if($reportName == "selled")
        {
            $data = StockProduct::join('soldItems','stockProducts.id','=','soldItems.stockId')
                             ->join('transaction','transaction.id','=','soldItems.transactionId')
                             ->join('products','products.id','=','stockProducts.productId')
                             ->select('products.reference','soldItems.transactionId','soldItems.quantity',
                             'soldItems.discount','soldItems.total','stockProducts.sizeeu','stockProducts.cup',
                             'stockProducts.color','stockProducts.barcode','soldItems.created_at')
                             ->whereNull('soldItems.deleted_at')
                             ->where('soldItems.created_at','>=',$from)
                             ->where('soldItems.created_at','<',$to)
                             ->get();
        }
        return $data ;
    }

    public function getTransactionItemsByBarcode (request $request)
    {
        $transactionId = $request->input('transactionId');
        $data = SoldItems::where('soldItems.transactionId',$transactionId)
                           ->join('stockProducts','stockProducts.id','=','soldItems.stockId')
                           ->join('products','stockProducts.productId','=','products.id') 
                           ->join('transaction','soldItems.transactionId','=','transaction.id') 
                           ->select('products.reference','stockProducts.sizeeu','stockProducts.id as stockId','stockProducts.color','stockProducts.cup'
                           ,'stockProducts.barcode','soldItems.id as soldItemId','soldItems.itemPrice as selledPrice','soldItems.quantity as selledQty'
                           ,'soldItems.discount as selledDiscount','soldItems.total as totalSelledItems',
                           'transaction.id as transactionId','transaction.subTotal as totalTransaction','transaction.totalDiscount'
                           ,'transaction.itemsCount as totalTransactionCount','transaction.orderTotal','transaction.created_at as transactionDate')
                           ->get();
        return $data ;
    }
    public function checkReturnedQty (request $request)
    {
        $soldItemId = $request->input('id');
        $newQty = $request->input('qty');
        $check = SoldItems::where('id',$soldItemId)->get();
        if( $newQty == null || $newQty == "" || $newQty <= 0 || $check[0]['quantity']<$newQty)
        return $check ;
        return 'valid';
    }
    public function checkReturnedDiscount (request $request)
    {
        $soldItemId = $request->input('id');
        $newDiscount = $request->input('discount');
        $check = SoldItems::where('id',$soldItemId)->get();
        if($check[0]['dicount']<$newDiscount ||  $newDiscount == null || $newDiscount == "" || $newDiscount > 100
        || $newDiscount < 0)
        return $check;
        return 'valid';
    }

    public function confirmReturnProccess (request $request)
    {
        $enteredItems = $request->input('enteredItems');
        $returnedItems = $request->input('returnedItems');
        $trasnactionItems = $request->input('trasnactionItems');
        $enteredItems = json_decode($enteredItems,true);
        $returnedItems = json_decode($returnedItems,true);
        $trasnactionItems = json_decode($trasnactionItems,true);
        //return $returnedItems;
        //return $trasnactionItems;
        //return $enteredItems;
        $createdTrans = [];
        if(count($enteredItems) > 0)
        {
            $enteredProductsTotal = 0;
            $enteredProductsdiscount = 0;
            $enteredProductsCount = 0;
            $enteredProductsOrderTotal = 0;
            if(count($trasnactionItems)>0)
            {
               
                $transaction = Transaction::where('id',$trasnactionItems[0]['transactionId'])->get();
            
                for($i=0;$i<count($enteredItems);$i++)
                {
                    $receipt =SoldItems::create(['stockId' => $enteredItems[$i]['id'],
                                                 'transactionId' =>  $transaction[0]['id'],
                                                 'companyPrice' => $enteredItems[$i]['companyPrice'],
                                                 'itemPrice' => $enteredItems[$i]['itemPrice'],
                                                 'quantity' => $enteredItems[$i]['qty'],
                                                 'discount' => $enteredItems[$i]['discount'],
                                                 'total' => $enteredItems[$i]['total']
                    ]);
                    $incrementQty = StockProduct::where('id',$enteredItems[$i]['id'])
                                    ->decrement('quantity', $enteredItems[$i]['qty']);
                    $enteredProductsTotal += $enteredItems[$i]['itemPrice']* $enteredItems[$i]['qty'];
                    $enteredProductsdiscount += ($enteredItems[$i]['itemPrice']*
                                                $enteredItems[$i]['qty']* $enteredItems[$i]['discount']/100);
                    $enteredProductsCount +=   $enteredItems[$i]['qty'];
                    $enteredProductsOrderTotal =  $enteredProductsTotal - $enteredProductsdiscount ;               
                }
                
                Transaction::where('id',$transaction[0]['id'])
                ->update([
                    'subTotal' => $enteredProductsTotal + $transaction[0]['subTotal'],
                    'totalDiscount' => $enteredProductsdiscount + $transaction[0]['totalDiscount'],
                    'itemsCount' => $enteredProductsCount + $transaction[0]['itemsCount'],
                    'orderTotal' => $enteredProductsOrderTotal + $transaction[0]['orderTotal']
                ]); 
                
            }
            
            else
            {
                $mytime = Carbon::today()->toDateString();
                $trans = Transaction::where('created_at','like', '%' .$mytime. '%')->max('transactionRef');
                if(count($trans) == 0)
                    $transactionRef= 1 ;
                else
                    $transactionRef = $trans +1 ;
                for($i=0;$i<count($enteredItems);$i++)
                {
                    $enteredProductsTotal += $enteredItems[$i]['itemPrice']* $enteredItems[$i]['qty'];
                    $enteredProductsdiscount += ($enteredItems[$i]['itemPrice']*
                                                $enteredItems[$i]['qty']* $enteredItems[$i]['discount']/100);
                    $enteredProductsCount +=   $enteredItems[$i]['qty'];
                    $enteredProductsOrderTotal =  $enteredProductsTotal - $enteredProductsdiscount ;   
                }
                $createdTrans = Transaction::create([
                    'subTotal' => $enteredProductsTotal ,
                    'transactionRef' => $transactionRef,
                    'totalDiscount' => $enteredProductsdiscount,
                    'itemsCount' => $enteredProductsCount,
                    'orderTotal' => $enteredProductsOrderTotal
                ]); 
                for($i=0;$i<count($enteredItems);$i++)
                {
                    $receipt =SoldItems::create([
                                'stockId' => $enteredItems[$i]['id'],
                                'transactionId' =>  $createdTrans['id'],
                                'companyPrice' => $enteredItems[$i]['companyPrice'],
                                'itemPrice' => $enteredItems[$i]['itemPrice'],
                                'quantity' => $enteredItems[$i]['qty'],
                                'discount' => $enteredItems[$i]['discount'],
                                'total' => $enteredItems[$i]['total']  
                    ]);
                    $incrementQty = StockProduct::where('id',$enteredItems[$i]['id'])
                    ->decrement('quantity', $enteredItems[$i]['qty']);
                }
            }
           
        }

        if(count($trasnactionItems) == 0)
        {
            if(count($createdTrans) >0)
                $createdTransactionWithNoRed = $createdTrans['id'];
                else $createdTransactionWithNoRed = null;
            for($x=0;$x<count($returnedItems);$x++)
            {
                ReturnProduct::create([
                        'stockId' => $returnedItems[$x]['id'],
                        'transactionId' => $createdTransactionWithNoRed,
                        'itemPrice' => $returnedItems[$x]['selledPrice'],
                        'quantity' => $returnedItems[$x]['selledQty'],
                        'discount' => $returnedItems[$x]['selledDiscount'],
                        'total' => $returnedItems[$x]['totalSelledItems'],
                        'hasRef' => 0
                ]);
                StockProduct::where('id',$returnedItems[$x]['id'])
                ->increment('quantity', $returnedItems[$x]['selledQty']);
            } 
        }
        else if(count($trasnactionItems) > 0)
        {
            $totalItemsCount = 0;
            for($x=0;$x<count($returnedItems);$x++)
            {
                ReturnProduct::create([
                        'stockId' => $returnedItems[$x]['stockId'],
                        'transactionId' => $returnedItems[$x]['transactionId'],
                        'itemPrice' => $returnedItems[$x]['selledPrice'],
                        'quantity' => $returnedItems[$x]['selledQty'],
                        'discount' => $returnedItems[$x]['selledDiscount'],
                        'total' => $returnedItems[$x]['totalSelledItems'],
                        'hasRef' => 1
                ]);
                StockProduct::where('id',$returnedItems[$x]['stockId'])
                            ->increment('quantity', $returnedItems[$x]['selledQty']);

                $checkSoldItems = SoldItems::where('id',$returnedItems[$x]['soldItemId'])->get();
                $transaction = Transaction::where('id',$returnedItems[$x]['transactionId'])->get();
                if($checkSoldItems[0]['quantity'] == $returnedItems[$x]['selledQty']) // if products quantity returned are the same of receipt count
                {
                    SoldItems::where('id',$returnedItems[$x]['soldItemId'])->delete();
                }
                else
                {
                    // if($checkSoldItems[0]['discount'] != 0)
                    //     $totalDiscount = $checkSoldItems[0]['total'] - 
                    //          ($returnedItems[$x]['selledQty']*$returnedItems[$x]['selledPrice'] 
                    //         -($returnedItems[$x]['selledQty']*$returnedItems[$x]['selledPrice']
                    //         *$returnedItems[$x]['selledDiscount'])/100);
                    // else $totalDiscount = 0;   
                    SoldItems::where('id',$returnedItems[$x]['soldItemId'])
                        ->update([
                            'quantity' => $checkSoldItems[0]['quantity'] - $returnedItems[$x]['selledQty'],
                            'total' =>   $checkSoldItems[0]['itemPrice'] - 
                                         ($checkSoldItems[0]['itemPrice']*$returnedItems[$x]['selledQty']*$checkSoldItems[0]['discount']/100)
                        ]);
                }
                $totalItemsCount +=   $returnedItems[$x]['selledQty'] ;
                // if($transaction[0]['itemsCount'] == $returnedItems[$x]['selledQty'])
                // {
                //     Transaction::where('id',$returnedItems[$x]['transactionId'])->delete();
                // }
            } 
           //return $transaction ;
            if($transaction[0]['itemsCount'] == $totalItemsCount)
            {
                Transaction::where('id',$transaction[0]['id'])->delete();
            }
            else
            {
                $transactionData =  SoldItems::where('transactionId',$transaction[0]['id'])->get();
                $subTotal = 0;
                $totalDiscount = 0;
                $itemsCount = 0;
                $orderTotal = 0;
                for($x=0;$x<count($transactionData);$x++)
                {
                    $subTotal+=$transactionData[$x]['itemPrice']*$transactionData[$x]['quantity'];
                    $totalDiscount+=  ($transactionData[$x]['itemPrice']*$transactionData[$x]['quantity']*$transactionData[$x]['discount']/100);
                    $itemsCount+=$transactionData[$x]['quantity'];
                }
                $orderTotal = $subTotal - $totalDiscount;
                Transaction::where('id',$transaction[0]['id'])
                            ->update([
                                'subTotal' => $subTotal,
                                'totalDiscount' => $totalDiscount,
                                'itemsCount' => $itemsCount,
                                'orderTotal' => $orderTotal
                            ]); 
            }
        }
        return 'out';
        
    }
    public function delay()
    {
        return 'ok';
    }
}
