SELECT 
  com.CompanyID AS company_id,
  cs.CS_Flag AS flage,
  com.CompanyDate AS register_time,
  com.CompanyCity AS city_name,
  com.CompanyAddress AS address,
  com.CompanyMobile AS mobile,
  com.CompanyPhone AS phone 
FROM
  etong_db_live_user.rsung_order_cs AS cs 
  LEFT JOIN etong_db_live_user.rsung_order_company AS com 
    ON cs.CS_Company = com.CompanyID 
WHERE cs.CS_Company in (518, 523)
LIMIT {BEGIN}, {STEP}