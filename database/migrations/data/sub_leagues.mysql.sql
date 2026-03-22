# Sunday, March 22nd , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for sub_leagues.mysql.sql
# Game name: sMLB
# Game date: 1970-07-13
# 
DROP TABLE IF EXISTS `sub_leagues`;
CREATE TABLE IF NOT EXISTS `sub_leagues` (`league_id` INT, `sub_league_id` INT, `name` VARCHAR(50), `abbr` VARCHAR(50), `gender` INT, `designated_hitter` TINYINT, PRIMARY KEY (`league_id`, `sub_league_id`));
insert ignore into `sub_leagues` (`league_id`, `sub_league_id`, `name`, `abbr`, `gender`, `designated_hitter`) VALUES (100, 0, "American League", "AL", 0, 0), (100, 1, "National League", "NL", 0, 0), (101, 0, "International League", "IL", 0, 0), (102, 0, "Pacific Coast League", "PCL", 0, 0), (103, 0, "American Association", "AA", 0, 0), (104, 0, "Southern League", "SOUL", 0, 0), (105, 0, "Eastern League", "EL", 0, 1), (106, 0, "Texas League", "TL", 0, 0), (107, 0, "Western Carolinas League", "WCRS", 0, 0), (108, 0, "California League", "CALL", 0, 0), (109, 0, "Florida State League", "FLOR", 0, 0), (110, 0, "Midwest League", "MIDW", 0, 0), (111, 0, "Carolina League", "CARL", 0, 0), (112, 0, "Northern League", "NORL", 0, 0), (113, 0, "Pioneer League", "PION", 0, 0), (114, 0, "Appalachian League", "APPY", 0, 0), (115, 0, "New York-Pennsylvania League", "NYPL", 0, 0), (116, 0, "Northwest League", "NORW", 0, 0), (117, 0, "Gulf Coast League", "GULF", 0, 0);

# 
# Dump completed
