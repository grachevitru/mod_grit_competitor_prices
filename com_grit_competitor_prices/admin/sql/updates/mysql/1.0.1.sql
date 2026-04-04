ALTER TABLE `#__competitor_prices`
  ADD COLUMN IF NOT EXISTS `competitor_url` varchar(1024) DEFAULT NULL AFTER `competitor_name`;
