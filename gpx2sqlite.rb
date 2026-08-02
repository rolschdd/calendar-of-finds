# frozen_string_literal: true

require "nokogiri"
require "sqlite3"
require "time"
require "tzinfo"

INPUT_PATH, DB_PATH = ARGV
if INPUT_PATH.nil? || DB_PATH.nil?
  warn "Usage: ruby #{$PROGRAM_NAME} input.xml output.db"
  exit 1
end

GROUNDSPEAK_TIMEZONE = TZInfo::Timezone.get("America/Los_Angeles")

# Get text of element with namespace-independent `local_name`.
def text_of(node, local_name)
  node.at_xpath(".//*[local-name()='#{local_name}']")&.text&.strip
end

# Converts `iso_timestamp` (e.g. "2018-05-26T19:00:00Z") to date in the format
# "YYYY-MM-DD".
# Some timezone calculation is needed here, because timestamps in XML are UTC
# of the time @ groundspeak.
def iso_to_date(iso_timestamp)
  raise(ArgumentError, "No valid ISO timestamp given, got nothing") if iso_timestamp.nil?

  parsed_time =
    begin
      Time.parse(iso_timestamp)
    rescue ArgumentError => _exception
      raise ArgumentError, "No valid ISO timestamp given, got: #{iso_timestamp}"
    end

  local_time = GROUNDSPEAK_TIMEZONE.to_local(parsed_time)

  local_time.strftime("%Y-%m-%d")
end

# start from scratch - no update of existing db
File.delete(DB_PATH) if File.exist?(DB_PATH)

db = SQLite3::Database.new(DB_PATH)
db.execute('PRAGMA journal_mode = WAL')
db.execute('PRAGMA synchronous = OFF')

db.execute <<~SQL
  CREATE TABLE caches (
    name       TEXT,
    difficulty TEXT,
    terrain    TEXT,
    size       TEXT,
    type       TEXT,
    log_type   TEXT,
    log_date   DATE
  )
SQL

insert = db.prepare(<<~SQL)
  INSERT INTO caches (name, difficulty, terrain, size, type, log_type, log_date)
  VALUES (?, ?, ?, ?, ?, ?, ?)
SQL

count_wpt  = 0
count_rows = 0

VALID_LOG_TYPES = ["Found it", "Attended", "Webcam Photo Taken"].freeze
CACHE_TYPE_MAPPING = {
  "Traditional Cache" => "Tradi",
  "Unknown (Mystery) Cache" => "Mystery",
  "Multi-cache" => "Multi",
  "Virtual Cache" => "Virtual",
  "Wherigo Cache" => "Wherigo",
  "Earthcache" => "Earth",
  "Letterbox Hybrid" => "Letterbox",
  "Locationless (Reverse) Cache" => "Locationless",
  "Cache In Trash Out Event" => "CITO",
  "Event Cache" => "Event",
  "Webcam Cache" => "Webcam",
  "Geocaching HQ Block Party" => "Blockparty",
  "Community Celebration Event" => "CCE",
  "Giga-Event Cache" => "Giga",
  "GPS Adventures Exhibit" => "Maze",
  "Mega-Event Cache" => "Mega"
}.freeze

db.transaction

File.open(INPUT_PATH) do |file|
  reader = Nokogiri::XML::Reader(file)

  reader.each do |node|
    next unless node.node_type == Nokogiri::XML::Reader::TYPE_ELEMENT
    next unless node.name == 'wpt'

    wpt = Nokogiri::XML(node.outer_xml).root
    count_wpt += 1

    name = text_of(wpt, 'name')
    difficulty = text_of(wpt, 'difficulty')
    terrain = text_of(wpt, 'terrain')
    size = text_of(wpt, 'container')

    # <type> node is ambigious, also under <logs> as log-type.
    type_node = wpt.at_xpath(
      ".//*[local-name()='type'][not(ancestor::*[local-name()='logs'])]"
    )
    type = type_node&.text&.strip

    if type
      type = type.gsub("Geocache|", "")
      type = CACHE_TYPE_MAPPING.fetch(type, type)
    end

    logs = wpt.xpath(".//*[local-name()='logs']/*[local-name()='log']")

    if logs.empty?
      warn "No log entry found for cache #{name}. Skipping."
    else
      logs.each do |log|
        log_type = log.at_xpath("./*[local-name()='type']")&.text&.strip
        next unless VALID_LOG_TYPES.include?(log_type)
        log_date = iso_to_date(log.at_xpath("./*[local-name()='date']")&.text&.strip)
        insert.execute(name, difficulty, terrain, size, type, log_type, log_date)
        count_rows += 1
      end
    end
  end
end

db.commit
insert.close
db.close

puts "Done: Wrote #{count_rows} rows into #{DB_PATH}"
