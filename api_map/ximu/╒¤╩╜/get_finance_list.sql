SELECT 
  f.FinanceID AS finance_id,
  o.OrderCompany AS company_id,
  o.OrderUserID AS client_id,
  o.OrderID AS order_id,
  c.ClientCompanyName AS client_name,
  FROM_UNIXTIME(
    f.FinanceUpDate,
    '%Y-%m-%d %H:%i:%s'
  ) AS tally_time,
  o.OrderTotal AS order_total,
  f.FinanceTotal AS finance_total,
  o.OrderStatus AS order_status 
FROM
  rsung_order_finance AS f 
  INNER JOIN rsung_order_orderinfo AS o 
    ON f.FinanceCompany = o.OrderCompany 
    AND f.FinanceClient = o.OrderUserID 
    AND f.FinanceOrderID = o.OrderID 
  LEFT JOIN rsung_order_client AS c 
    ON o.OrderCompany = c.ClientCompany 
    AND o.OrderUserID = c.ClientID 
	
WHERE o.OrderCompany in (518,523)
LIMIT {BEGIN}, {STEP}