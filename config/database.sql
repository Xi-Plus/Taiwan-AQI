SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `taiwan_aqi_area` (
  `no` tinyint(4) NOT NULL DEFAULT '0',
  `area` varchar(20) NOT NULL,
  `name` varchar(10) NOT NULL DEFAULT '未分類'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `taiwan_aqi_city` (
  `area` varchar(20) NOT NULL,
  `name` varchar(10) NOT NULL,
  `PSI` varchar(5) NOT NULL DEFAULT '',
  `O3` varchar(5) NOT NULL DEFAULT '',
  `PM25` varchar(5) NOT NULL DEFAULT '',
  `PM10` varchar(5) NOT NULL DEFAULT '',
  `CO` varchar(5) NOT NULL DEFAULT '',
  `SO2` varchar(5) NOT NULL DEFAULT '',
  `NO2` varchar(5) NOT NULL DEFAULT '',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `taiwan_aqi_follow` (
  `tmid` varchar(50) NOT NULL,
  `city` varchar(15) NOT NULL,
  `level` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `taiwan_aqi_input` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `input` text NOT NULL,
  `hash` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `taiwan_aqi_log` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` text NOT NULL,
  `hash` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `taiwan_aqi_user` (
  `uid` varchar(25) NOT NULL,
  `tmid` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `taiwan_aqi_area`
  ADD UNIQUE KEY `area` (`area`);

ALTER TABLE `taiwan_aqi_city`
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `taiwan_aqi_input`
  ADD UNIQUE KEY `hash` (`hash`);

ALTER TABLE `taiwan_aqi_log`
  ADD UNIQUE KEY `hash` (`hash`);

ALTER TABLE `taiwan_aqi_user`
  ADD UNIQUE KEY `tmid` (`tmid`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
