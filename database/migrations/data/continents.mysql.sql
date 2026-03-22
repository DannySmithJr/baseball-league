# Sunday, March 22nd , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for continents.mysql.sql
# Game name: sMLB
# Game date: 1970-07-13
# 
DROP TABLE IF EXISTS `continents`;
CREATE TABLE IF NOT EXISTS `continents` (`continent_id` INT, `name` VARCHAR(50), `abbreviation` VARCHAR(50), `demonym` VARCHAR(50), `population` INT, `main_language_id` INT, PRIMARY KEY (`continent_id`));
insert into `continents` (`continent_id`, `name`, `abbreviation`, `demonym`, `population`, `main_language_id`) VALUES (1, "Africa", "AF", "African", 1000010001, -1), (2, "Asia", "AS", "Asian", 2147483647, -1), (3, "Europe", "EU", "European", 731000000, -1), (4, "North America", "NA", "North American", 528720588, -1), (5, "Oceania", "OC", "Oceanic", 35670000, -1), (6, "South America", "SA", "South American", 385742554, -1);

# 
# Dump completed
