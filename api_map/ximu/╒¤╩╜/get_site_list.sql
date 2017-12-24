SELECT 
  cp.CompanyID AS company_id,
  s.SiteID AS site_id,
  s.SiteNO AS site_id_multi,
  s.SiteName AS site_name 
FROM
  rsung_order_site AS s 
  LEFT JOIN etong_db_live_user.rsung_order_company AS cp 
    ON cp.CompanyID = s.CompanyID 
WHERE s.CompanyID in (518,523)
LIMIT {BEGIN}, {STEP}