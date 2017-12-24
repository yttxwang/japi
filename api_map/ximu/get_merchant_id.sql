SELECT 
  com.CompanyID AS company_id,
  com.CompanyContact AS contact,
  com.CompanyMobile AS mobile,
  com.CompanyPhone AS phone,
  con.IDCard AS id_card 
FROM
  db_xxasyy_15_user.rsung_order_company AS com 
  LEFT JOIN db_xxasyy_15_user.rsung_order_company_data AS con 
    ON com.CompanyID = con.CompanyID 
WHERE com.CompanyID 
LIMIT {BEGIN}, {STEP}