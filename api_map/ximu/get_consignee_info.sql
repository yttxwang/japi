SELECT 
  ar.AreaID AS city_id,
  ar.AreaName AS city_name,
  addr.AddressID as address_id,
  addr.CompanyID AS company_id,
  addr.AddressClient AS client_id,
  addr.AddressCompany AS consignee_target,
  addr.AddressContact AS consignee,
  addr.AddressPhone AS phone,
  addr.AddressAddress AS address 
FROM
  rsung_order_client AS c 
  LEFT JOIN rsung_order_address AS addr 
    ON c.ClientCompany = addr.CompanyID 
    AND c.ClientID = addr.AddressClient 
  LEFT JOIN rsung_order_area AS ar 
    ON c.ClientCompany = ar.AreaCompany 
    AND c.ClientArea = ar.AreaID 
WHERE addr.CompanyID 
  limit {BEGIN},{STEP}