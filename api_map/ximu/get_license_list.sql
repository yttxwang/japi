SELECT 
  com.CompanyID AS company_id,
  con.BusinessCard AS business_license,
  con.IDLicence AS monopoly_license,
  con.IDGP AS identification_license
FROM
  db_xxasyy_15_user.rsung_order_company AS com 
  LEFT JOIN db_xxasyy_15_user.rsung_order_company_data AS con 
    ON com.CompanyID = con.CompanyID 
WHERE com.CompanyID 
LIMIT {BEGIN}, {STEP}