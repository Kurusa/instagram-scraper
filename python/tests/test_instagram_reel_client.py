import importlib.util
from pathlib import Path
import sys
import unittest


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


if __name__ == "__main__":
    unittest.main()
