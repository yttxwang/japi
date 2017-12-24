<?php 

class ShoppingCart { 

    var $items; 

    function add_items($product_id, $product_name, $product_color, $product_price, $qty) 
    { 
        if(empty($qty)) $qty = 1;
	    if(!empty($product_color))
		{
		   $kid = $product_id."_".ord($product_color);
		}else{
		   $kid = $product_id;
		}
	   
	   if(@array_key_exists($kid, $this->items)) 
       { 

			$this->update_items($kid, $qty)
			//$this->items[$kid]['num']   = $this->items[$kid]['num']+$qty;

	   }else{
			$this->items[$kid]['id']    = $product_id;
			$this->items[$kid]['name']  = $product_name;
			$this->items[$kid]['color'] = $product_color;
			$this->items[$kid]['price'] = $product_price;
			$this->items[$kid]['num']   = $qty;		   
	   }
    } 

    function update_items($kid, $qty) 
    { 
       if(array_key_exists($kid, $this->items)) 
       { 
          if($this->items[$kid]['num'] > $qty) 
          { 
             $this->items[$kid]['num'] -= ($this->items[$kid]['num']-$qty); 
          } 
          if($this->items[$kid]['num']<$qty) 
          { 
             $this->items[$kid]['num'] += abs($this->items[$kid]['num']-$qty); 
          } 
          if($qty==0) 
          { 
             unset($this->items[$kid]); 
          } 
       } 
    } 
    
    function remove_item($kid) 
    { 
       if(array_key_exists($kid, $this->items)) 
       { 
          unset($this->items[$kid]); 
       } 
    } 

    function show_cart() 
    { 
       return $this->items; 
    } 

} 

//$cart = new ShoppingCart; 
//
//$cart->add_items("2", "009中在要", "红", "0.9", "5");
//$cart->add_items("2", "009中在要", "红", "0.9", "2"); 
//$cart->add_items("4", "009中在要", "红", "0.9", "6"); 
//
//$cart_items = $cart->show_cart(); 
//
//foreach($cart_items as $key => $value) 
//{ 
//    echo "Item name = $key; Item quantity: ".$value['name']."-".$value['num']." <br>"; 
//} 
//
//$cart->update_items("2", 28); 
//$cart->update_items("4", 7); 
//$cart_items=$cart->show_cart(); 
//
//echo "================<br>"; 
//
//foreach($cart_items as $key=>$value) 
//{ 
//    echo "$key = ".$value['name']."-".$value['num']."<br>"; 
//} 
//
//$cart->remove_item("2"); 
//$cart_items=$cart->show_cart(); 
//
//echo "================<br>"; 
//
//foreach($cart_items as $key=>$value) 
//{ 
//    echo "$key = ".$value['name']."-".$value['num']."<br>"; 
//} 


?> 
