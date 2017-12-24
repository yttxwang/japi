SELECT 
  c.ClientCompany as company_id,
  uc.CompanyName AS comapny_name,
  c.`ClientID` AS client_id,
  c.`ClientCompanyName` AS client_name,
  c.`ClientTrueName` AS  normal_consignee,
  c.`ClientPhone` AS normal_phone,
  c.`ClientMobile` AS normal_mobile,
  c.`ClientAdd` AS normal_address,
  (
    CASE
      c.`ClientFlag` 
      WHEN 9 
      THEN '待审核' 
      WHEN 0 
      THEN '已审核' 
    END
  ) AS approve_status,
  (
    CASE
      c.`ClientFlag` 
      WHEN 1 
      THEN '冻结' 
      ELSE '正常' 
    END
  ) AS is_normal,
  FROM_UNIXTIME(
    c.`ClientDate`,
    '%Y-%m-%d %H:%i:%s'
  ) AS register_time,
  c.`ClientMobile` AS register_mobile,
  FROM_UNIXTIME(
    c.`ClientDate`,
    '%Y-%m-%d %H:%i:%s'
  ) AS approve_time,
  a.AreaName AS register_city,
  (SELECT 
    FROM_UNIXTIME(
      ul.LoginDate,
      '%Y-%m-%d %H:%i:%s'
    ) 
  FROM
    etong_db_live_user.rsung_order_login_client_log AS ul 
  WHERE c.`ClientID` = ul.LoginClient 
    AND c.`ClientCompany` = ul.LoginCompany 
  ORDER BY ul.LoginDate DESC 
  LIMIT 1) AS lately_login_time,
  (SELECT 
    ul.LoginIP 
  FROM
    etong_db_live_user.rsung_order_login_client_log AS ul 
  WHERE c.`ClientID` = ul.LoginClient 
    AND c.`ClientCompany` = ul.LoginCompany 
  ORDER BY ul.LoginDate DESC 
  LIMIT 1) AS ip,
  (SELECT 
    ul.LoginUrl 
  FROM
    etong_db_live_user.rsung_order_login_client_log AS ul 
  WHERE c.`ClientID` = ul.LoginClient 
    AND c.`ClientCompany` = ul.LoginCompany 
  ORDER BY ul.LoginDate DESC 
  LIMIT 1) AS login_type 
FROM
  `rsung_order_client` AS c 
  LEFT JOIN rsung_order_area AS a 
    ON c.`ClientCompany` = a.AreaCompany 
    AND c.`ClientArea` = a.AreaID 
  LEFT JOIN etong_db_live_user.rsung_order_company AS uc 
    ON c.`ClientCompany` = uc.CompanyID 
WHERE c.`ClientCompany` IN (518, 523) 
  AND c.`ClientCompanyName` NOT LIKE '%测试%' 
LIMIT {BEGIN}, {STEP}