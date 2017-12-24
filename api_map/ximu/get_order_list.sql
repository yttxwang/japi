SELECT 
  o.OrderID AS order_id,
  o.OrderSN AS order_sn,
  o.OrderPayType AS pay_type,
  o.OrderUserID AS client_id,
  c.ClientCompanyName AS client_name,
  o.OrderStatus AS order_status,
  o.OrderFrom AS order_from,
  o.OrderTotal AS order_total,
  o.OrderIntegral pay_actual,
  (
    FROM_UNIXTIME(
      o.OrderDate,
      '%Y-%m-%d %H:%i:%s'
    )
  ) AS order_date,
  o.OrderReceiveCompany AS consignee_target,
  o.OrderReceiveName AS consignee,
  o.OrderReceivePhone AS phone,
  o.OrderReceiveAdd AS address,
  con.ConsignmentDate AS complete_date
FROM
  rsung_order_orderinfo AS o 
  LEFT JOIN rsung_order_client AS c 
    ON o.OrderCompany = c.ClientCompany 
    AND o.OrderUserID = c.ClientID 
  LEFT JOIN rsung_order_consignment AS con 
    ON o.OrderCompany = con.ConsignmentCompany 
    AND o.OrderUserID = con.ConsignmentClient 
    AND o.OrderSN = con.ConsignmentOrder 
	
WHERE o.OrderCompany 
LIMIT {BEGIN}, {STEP}