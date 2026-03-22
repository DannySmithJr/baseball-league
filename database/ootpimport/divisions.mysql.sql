# Thursday, March 19th , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for divisions.mysql.sql
# Game name: sMLB
# Game date: 1970-07-06
# 
DROP TABLE IF EXISTS `divisions`;
CREATE TABLE IF NOT EXISTS `divisions` (`league_id` INT, `sub_league_id` INT, `division_id` INT, `name` VARCHAR(50), `gender` INT, PRIMARY KEY (`league_id`, `sub_league_id`, `division_id`));
insert into `divisions` (`league_id`, `sub_league_id`, `division_id`, `name`, `gender`) VALUES (100, 0, 0, "East Division", 0), (100, 0, 1, "West Division", 0), (100, 1, 0, "East Division", 0), (100, 1, 1, "West Division", 0), (101, 0, 0, "", 0), (102, 0, 0, "North", 0), (102, 0, 1, "South", 0), (103, 0, 0, "East", 0), (103, 0, 1, "West", 0), (104, 0, 0, "", 0), (105, 0, 0, "", 0), (106, 0, 0, "East", 0), (106, 0, 1, "West", 0), (107, 0, 0, "", 0), (108, 0, 0, "", 0), (109, 0, 0, "East", 0), (109, 0, 1, "West", 0), (110, 0, 0, "", 0), (111, 0, 0, "", 0), (112, 0, 0, "", 0), (113, 0, 0, "", 0), (114, 0, 0, "", 0), (115, 0, 0, "", 0), (116, 0, 0, "North", 0), (116, 0, 1, "South", 0), (117, 0, 0, "", 0);

# 
# Dump completed
