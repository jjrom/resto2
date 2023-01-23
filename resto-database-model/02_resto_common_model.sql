--
-- resto core common model
--

--
-- resto core model is stored under {DATABASE_COMMON_SCHEMA} schema
--
CREATE SCHEMA IF NOT EXISTS __DATABASE_COMMON_SCHEMA__;

--
-- users table list user informations
--
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.user (

    -- Unique identifier based on resto serial (timestamp)
    "id"                BIGINT PRIMARY KEY DEFAULT public.timestamp_to_id(clock_timestamp()),

    -- Email adress
    email               TEXT NOT NULL UNIQUE,

    -- By default concatenation of firstname lastname
    name                TEXT,

    -- First name
    firstname           TEXT,

    -- Last name
    lastname            TEXT,

    -- User description
    bio                 TEXT,

    -- Array of groups referenced by __DATABASE_COMMON_SCHEMA__.group.id
    groups              INTEGER[],

    -- User lang
    lang                TEXT,

    -- User country
    country             TEXT,

    -- User organization
    organization        TEXT,

    -- User organization country
    organizationcountry TEXT,

    -- Comm separated list of additionnal properties
    flags               TEXT,

    -- Array of topics of interest name. Reference __DATABASE_COMMON_SCHEMA__.topic.name
    topics              TEXT[],

    -- Password stored as sha1 with salt
    password            TEXT NOT NULL,

    -- Url to user's picture
    picture             TEXT,

    -- Registration timestamp of the user
    registrationdate    TIMESTAMP,

    -- Token used for password reset
    resettoken          TEXT,

    -- Timestamp until which the reset token is valid
    resetexpire         TIMESTAMP,

    -- User is activated (1) once registration is completed (i.e. email adress verified)
    activated           INTEGER,

    -- Number of followers. Used by Social add-on
    followers           INTEGER,

    -- Number of followings. Used by Social add-on
    followings          INTEGER,

    -- Who validated the user (usually admin)
    validatedby         TEXT, 

    -- Date of validation
    validationdate      TIMESTAMP, -- Validation date

    -- External Identity provider (Facebook, google, etc.)
    externalidp         JSON, 

    -- Free application settings
    settings            JSON

);

--
-- Followers table
--
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.follower (

    -- Reference __DATABASE_COMMON_SCHEMA__.user.id
    userid              BIGINT NOT NULL REFERENCES __DATABASE_COMMON_SCHEMA__.user (id) ON DELETE CASCADE,

    -- Reference __DATABASE_COMMON_SCHEMA__.user.id
    followerid          BIGINT NOT NULL REFERENCES __DATABASE_COMMON_SCHEMA__.user (id) ON DELETE CASCADE,

    -- Timestamp of relationship creation
    created             TIMESTAMP,

    PRIMARY KEY (userid, followerid)

);

--
-- groups table list
--
CREATE SEQUENCE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.group_id_seq START 100 INCREMENT 1;
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.group (

    -- Group identifier is a serial
    "id"                INTEGER PRIMARY KEY DEFAULT nextval('__DATABASE_COMMON_SCHEMA__.group_id_seq'),

    -- Name of the group
    name                TEXT NOT NULL,

    -- Description
    description         TEXT,

    -- Owner of the group
    owner               BIGINT,

    -- Timestamp of group creation
    created             TIMESTAMP

);

--
-- topics table
--
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.topic (

    -- Topic of interest name
    name                TEXT PRIMARY KEY,

    -- Description
    description         TEXT

);

--
-- rights table list user rights on collection
--
CREATE SEQUENCE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.right_id_seq START 100 INCREMENT 1;
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.right (

    -- Unique id
    gid                 INTEGER PRIMARY KEY DEFAULT nextval('__DATABASE_COMMON_SCHEMA__.right_id_seq'), 

    -- Reference to __DATABASE_COMMON_SCHEMA__.user.id
    userid              BIGINT,

    -- Reference to __DATABASE_COMMON_SCHEMA__.group.groupid
    groupid             BIGINT,

    -- Reference the schema name the collection belongs to
    target              TEXT,

    -- Reference __DATABASE_TARGET_SCHEMA__.collection.id
    collection          TEXT,

    -- Reference __DATABASE_TARGET_SCHEMA__.feature.id
    featureid           UUID,

    -- Has right to download = 1. Otherwise 0
    download            INTEGER DEFAULT 0,

    -- Has right to visualize = 1. Otherwise 0
    visualize           INTEGER DEFAULT 0,

    -- Has right to create a collection = 1. Otherwise 0
    createcollection    INTEGER DEFAULT 0

);

--
-- Shared links are temporaty links available when you know the url
--
CREATE SEQUENCE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.sharedlink_id_seq START 100 INCREMENT 1;
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.sharedlink (

    -- Not used
    gid                 INTEGER PRIMARY KEY DEFAULT nextval('__DATABASE_COMMON_SCHEMA__.sharedlink_id_seq'),

    -- Token
    token               TEXT UNIQUE NOT NULL,

    -- Url that can be requested with this token
    url                 TEXT NOT NULL,

    -- Validity in the future - if request time is greater than validity then 403
    validity            TIMESTAMP,

    -- Original requester of this link. Reference to __DATABASE_COMMON_SCHEMA__.user.id
    userid              BIGINT

);

--
-- Revoked tokens table
-- On insert trigger delete entries older than 48 hours
--
CREATE SEQUENCE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.revokedtoken_id_seq START 100 INCREMENT 1;
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.revokedtoken (

    -- Unique identifier (not used)
    gid                 INTEGER PRIMARY KEY DEFAULT nextval('__DATABASE_COMMON_SCHEMA__.revokedtoken_id_seq'),

    -- Token either JWT or RJWT
    token               TEXT UNIQUE NOT NULL,

    -- Date of token creation
    created             TIMESTAMP NOT NULL DEFAULT now(),

    -- Usually the exp time of the token - after this date the token is no more valid and can be removed from this table
    validuntil          TIMESTAMP

);

--
-- Logs table stores all user requests
--
CREATE TABLE IF NOT EXISTS __DATABASE_COMMON_SCHEMA__.log (

    gid                 SERIAL PRIMARY KEY,

    -- Reference __DATABASE_COMMON_SCHEMA__.user id
    userid              BIGINT,

    -- Http method (i.e. GET,PUT,POST,DELETE)
    method              TEXT,

    -- Time of query
    querytime           TIMESTAMP,

    -- Query path
    path                TEXT,

    -- Query params
    query               TEXT,

    -- Query initiator IP address
    ip                  TEXT

);

-- ------------------------- INSERT ----------------------------

-- [TABLE __DATABASE_COMMON_SCHEMA__.right] admin rights
INSERT INTO __DATABASE_COMMON_SCHEMA__.right
    (groupid, target, collection, download, visualize, createcollection)
SELECT 0,'*','*',1,1,1
WHERE
    NOT EXISTS (
        SELECT groupid, target, collection FROM __DATABASE_COMMON_SCHEMA__.right WHERE groupid = 0 AND target = '*' AND collection = '*'
    );

-- [TABLE __DATABASE_COMMON_SCHEMA__.group] default groups
INSERT INTO __DATABASE_COMMON_SCHEMA__.group (id, name, description, created) VALUES (0, 'admin', 'Special group for admin', now()) ON CONFLICT (id) DO NOTHING;
INSERT INTO __DATABASE_COMMON_SCHEMA__.group (id, name, description, created) VALUES (100, 'default', 'Default group', now()) ON CONFLICT (id) DO NOTHING;

-- ------------------------- INDEXES ----------------------------

-- [TABLE __DATABASE_COMMON_SCHEMA__.user]
CREATE INDEX IF NOT EXISTS idx_resettoken_user ON __DATABASE_COMMON_SCHEMA__.user (resettoken);
CREATE INDEX IF NOT EXISTS idx_name_user ON __DATABASE_COMMON_SCHEMA__.user USING gist (name gist_trgm_ops);

-- [TABLE __DATABASE_COMMON_SCHEMA__.follower]
CREATE INDEX IF NOT EXISTS idx_userid_follower ON __DATABASE_COMMON_SCHEMA__.follower (userid);
CREATE INDEX IF NOT EXISTS idx_followerid_follower ON __DATABASE_COMMON_SCHEMA__.follower (followerid);

-- [TABLE __DATABASE_COMMON_SCHEMA__.right]
CREATE INDEX IF NOT EXISTS idx_userid_right ON __DATABASE_COMMON_SCHEMA__.right (userid);
CREATE INDEX IF NOT EXISTS idx_groupid_right ON __DATABASE_COMMON_SCHEMA__.right (groupid);

-- [TABLE __DATABASE_COMMON_SCHEMA__.group]
CREATE UNIQUE INDEX IF NOT EXISTS idx_uname_group ON __DATABASE_COMMON_SCHEMA__.group (normalize(name));
CREATE INDEX IF NOT EXISTS idx_name_group ON __DATABASE_COMMON_SCHEMA__.group USING GIN (normalize(name) gin_trgm_ops);

-- [TABLE __DATABASE_COMMON_SCHEMA__.log]
CREATE INDEX IF NOT EXISTS idx_userid_log ON __DATABASE_COMMON_SCHEMA__.log (userid);
CREATE INDEX IF NOT EXISTS idx_querytime_log ON __DATABASE_COMMON_SCHEMA__.log (querytime);
CREATE INDEX IF NOT EXISTS idx_method_log ON __DATABASE_COMMON_SCHEMA__.log (method);
