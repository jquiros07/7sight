import redis

import config


def connect():
    return redis.Redis(host=config.REDIS_HOST, port=config.REDIS_PORT, decode_responses=True)


def ensure_group(client):
    """Create the consumer group if it doesn't exist yet. Safe to call every startup."""
    try:
        client.xgroup_create(config.STREAM_NAME, config.CONSUMER_GROUP, id="0", mkstream=True)
    except redis.ResponseError as error:
        if "BUSYGROUP" not in str(error):
            raise


def reclaim_stale(client):
    """Reclaim messages a crashed worker never acknowledged, so they get retried.

    Returns a list of (message_id, fields) tuples claimed this round.
    """
    _, messages, _ = client.xautoclaim(
        config.STREAM_NAME,
        config.CONSUMER_GROUP,
        config.CONSUMER_NAME,
        min_idle_time=config.STALE_IDLE_MS,
        start_id="0-0",
        count=10,
    )
    return messages


def read_one(client, block_ms=5000):
    """Block for a new message. Returns (message_id, fields) or None on timeout."""
    response = client.xreadgroup(
        config.CONSUMER_GROUP,
        config.CONSUMER_NAME,
        {config.STREAM_NAME: ">"},
        count=1,
        block=block_ms,
    )
    if not response:
        return None

    _, messages = response[0]
    return messages[0] if messages else None


def ack(client, message_id):
    client.xack(config.STREAM_NAME, config.CONSUMER_GROUP, message_id)
