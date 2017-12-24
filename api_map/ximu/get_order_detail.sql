SELECT 
c.ID as cart_id,
  o.OrderID AS order_id,
  o.OrderSN AS order_sn,
  o.OrderPayType AS pay_type,
  o.OrderCompany AS company_id,
  oc.CompanyName AS company_name,
  o.OrderUserID AS client_id,
  cl.ClientCompanyName AS client_name,
  o.OrderStatus AS order_status,
  c.ContentID AS product_id,
  i.Name AS product_name,
  -- i.Price1 AS price,
  i.SiteID AS site_id,
  c.ContentNumber AS number,
  c.ContentPercent AS percent,
  c.ContentPrice order_price,
  o.OrderStatus AS order_status,
  FROM_UNIXTIME(os.Date, '%Y-%m-%d %H:%i:%s') AS approve_time 
FROM
  rsung_order_orderinfo AS o 
  LEFT JOIN rsung_order_cart AS c 
    ON o.OrderCompany = c.CompanyID 
    AND o.OrderUserID = c.ClientID 
    AND o.OrderID = c.OrderID 
  LEFT JOIN rsung_order_client AS cl 
    ON o.OrderCompany = cl.ClientCompany 
    AND o.OrderUserID = cl.ClientID 
  LEFT JOIN rsung_order_content_index AS i 
    ON c.CompanyID = i.CompanyID 
    AND c.ContentID = i.ID 
  LEFT JOIN rsung_order_ordersubmit AS os 
    ON o.OrderCompany = os.CompanyID 
    AND o.OrderID = os.OrderID 
    AND os.Status = '审核订单' 
  LEFT JOIN db_xxasyy_15_user.rsung_order_company AS oc 
    ON o.OrderCompany = oc.CompanyID 
	
WHERE o.OrderCompany 
LIMIT {BEGIN}, {STEP}