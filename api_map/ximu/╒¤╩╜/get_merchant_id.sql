SELECT 
  com.CompanyID AS company_id,
  com.CompanyContact AS contact,
  com.CompanyMobile AS mobile,
  com.CompanyPhone AS phone,
  con.IDCard AS id_card 
FROM
  etong_db_live_user.rsung_order_company AS com 
  LEFT JOIN etong_db_live_user.rsung_order_company_data AS con 
    ON com.CompanyID = con.CompanyID 
WHERE com.CompanyID in (518, 523)
LIMIT {BEGIN}, {STEP}