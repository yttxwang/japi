SELECT 
  i.ID AS product_id,
  i.Name AS product_name,
  i.Price1 AS price,
  i.SiteID AS site_id,
  s.SiteNO AS site_id_multi,
  s.SiteName AS site_name,
  i.BrandID AS brand_id,
  b.BrandName AS brand_name,
  (
    CASE
      i.FlagID 
      WHEN 1 
      THEN '下架' 
      WHEN 0 
      THEN '在售' 
    END
  ) AS product_status,
  cp.CompanyID AS company_id,
  cp.CompanyName AS company_name 
FROM
  rsung_order_content_index AS i 
  LEFT JOIN db_xxasyy_15_user.rsung_order_company AS cp 
    ON i.CompanyID = cp.CompanyID 
  LEFT JOIN rsung_order_site AS s 
    ON i.CompanyID = s.CompanyID 
    AND i.SiteID = s.SiteID 
  LEFT JOIN rsung_order_brand AS b 
    ON i.CompanyID = b.CompanyID 
    AND i.BrandID = b.BrandID 
WHERE i.CompanyID 
LIMIT {BEGIN}, {STEP}