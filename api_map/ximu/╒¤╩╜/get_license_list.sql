SELECT 
  com.CompanyID AS company_id,
  con.BusinessCard AS business_license,
  con.IDLicence AS monopoly_license,
  con.IDGP AS identification_license
FROM
  etong_db_live_user.rsung_order_company AS com 
  LEFT JOIN etong_db_live_user.rsung_order_company_data AS con 
    ON com.CompanyID = con.CompanyID 
WHERE com.CompanyID in (518, 523)
LIMIT {BEGIN}, {STEP}