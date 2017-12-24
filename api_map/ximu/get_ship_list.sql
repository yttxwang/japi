SELECT 
	con.ConsignmentID as ship_id,
  o.OrderCompany AS company_id,
  o.OrderUserID AS client_id,
  c.ClientCompanyName as ship_company_name,
  o.OrderID AS order_id,
  o.OrderSN AS order_sn,
  con.ConsignmentNO AS ship_order,
  con.ConsignmentDate AS ship_time,
  FROM_UNIXTIME(
    con.InputDate,
    '%Y-%m-%d %H:%i:%s'
  ) AS input_time
   
FROM
  rsung_order_orderinfo AS o 
  INNER JOIN rsung_order_consignment AS con 
    ON o.OrderCompany = con.ConsignmentCompany 
    AND o.OrderUserID = con.ConsignmentClient 
  LEFT JOIN rsung_order_client AS c 
    ON o.OrderCompany = c.ClientCompany 
    AND o.OrderUserID = c.ClientID 
WHERE o.OrderCompany 
LIMIT {BEGIN}, {STEP}