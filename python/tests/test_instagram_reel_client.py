import importlib.util
from pathlib import Path
import sys
import time
import unittest
from unittest.mock import Mock, patch


MODULE_PATH = Path(__file__).resolve().parents[1] / "instagram_reel_client.py"
SPEC = importlib.util.spec_from_file_location("instagram_reel_client", MODULE_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class ShortcodeConversionTest(unittest.TestCase):
    def test_converts_shortcode_to_media_id(self) -> None:
        self.assertEqual(
            "3949324184798665211",
            MODULE.shortcode_to_media_id("DbO0WvxyJn7"),
        )


class ProfileResolutionTest(unittest.TestCase):
    def test_resolves_profile_pk_from_username(self) -> None:
        response = Mock()
        response.status_code = 200
        response.text = '{"data":{"xig_user_by_username":{"pk":"348796639"}}}'
        response.json.return_value = {
            "data": {
                "xig_user_by_username": {
                    "pk": "348796639",
                    "username": "itsheidiwong",
                },
            },
        }
        session_client = Mock()
        session_client.post.return_value = response
        anonymous_session = MODULE.AnonymousSession(
            client=session_client,
            csrf_token="csrf",
            lsd_token="lsd",
            created_at=time.monotonic(),
        )
        client = MODULE.InstagramReelClient()

        with patch.object(client, "_get_session", return_value=anonymous_session):
            result = client.handle({
                "action": "resolve_profile",
                "username": "@itsheidiwong",
            })

        self.assertTrue(result["ok"])
        self.assertEqual(
            {"id": "348796639", "username": "itsheidiwong"},
            result["profile"],
        )
        request = session_client.post.call_args
        self.assertEqual(MODULE.PROFILE_DOC_ID, request.kwargs["data"]["doc_id"])
        self.assertEqual(
            '{"username":"itsheidiwong"}',
            request.kwargs["data"]["variables"],
        )


if __name__ == "__main__":
    unittest.main()
