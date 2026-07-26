#!/usr/bin/env python3

from __future__ import annotations

import json
import re
import sys
import time
from dataclasses import dataclass
from typing import Any
from urllib.parse import quote, urlencode

from curl_cffi import requests


INSTAGRAM_BASE_URL = "https://www.instagram.com"
DETAIL_GRAPHQL_URL = f"{INSTAGRAM_BASE_URL}/api/graphql"
RULING_URL = f"{INSTAGRAM_BASE_URL}/api/v1/web/get_ruling_for_content/"
DETAIL_DOC_ID = "27130156389949648"
DETAIL_FRIENDLY_NAME = "PolarisLoggedOutDesktopWWWPostRootContentQuery"
PROFILE_DOC_ID = "36836636079261063"
PROFILE_FRIENDLY_NAME = "PolarisLoggedOutDesktopWWWProfileRootContentQuery"
SHORTCODE_ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_"


def shortcode_to_media_id(shortcode: str) -> str:
    value = 0

    for character in shortcode:
        value = value * 64 + SHORTCODE_ALPHABET.index(character)

    return str(value)


def proxy_url(proxy: dict[str, Any] | None) -> str | None:
    if not proxy:
        return None

    host = proxy["host"]
    port = proxy["port"]
    username = proxy.get("username")
    password = proxy.get("password")

    if username is None:
        return f"http://{host}:{port}"

    encoded_username = quote(str(username), safe="")
    encoded_password = quote(str(password or ""), safe="")

    return f"http://{encoded_username}:{encoded_password}@{host}:{port}"


def proxy_key(proxy: dict[str, Any] | None) -> str:
    if not proxy:
        return "direct"

    return "|".join([
        str(proxy.get("host", "")),
        str(proxy.get("port", "")),
        str(proxy.get("username", "")),
        str(proxy.get("password", "")),
    ])


@dataclass
class AnonymousSession:
    client: requests.Session
    csrf_token: str | None
    lsd_token: str | None
    created_at: float


class InstagramReelClient:
    def __init__(self) -> None:
        self.sessions: dict[str, AnonymousSession] = {}

    def handle(self, command: dict[str, Any]) -> dict[str, Any]:
        interactions: list[dict[str, Any]] = []

        try:
            if command.get("action") == "resolve_profile":
                return self._resolve_profile(command, interactions, allow_session_retry=True)

            return self._fetch(command, interactions, allow_session_retry=True)
        except Exception as exception:
            return {
                "ok": False,
                "error": f"{type(exception).__name__}: {exception}",
                "interactions": interactions,
            }

    def _fetch(
        self,
        command: dict[str, Any],
        interactions: list[dict[str, Any]],
        allow_session_retry: bool,
    ) -> dict[str, Any]:
        shortcode = str(command["shortcode"])
        media_id = str(command.get("media_id") or shortcode_to_media_id(shortcode))
        app_id = str(command.get("app_id") or "936619743392459")
        impersonate = str(command.get("impersonate") or "chrome")
        timeout = int(command.get("request_timeout_seconds") or 45)
        session_ttl = int(command.get("session_ttl_seconds") or 300)
        proxy = command.get("proxy")
        key = proxy_key(proxy)

        anonymous_session = self._get_session(
            key=key,
            proxy=proxy,
            impersonate=impersonate,
            timeout=timeout,
            ttl_seconds=session_ttl,
            interactions=interactions,
        )

        headers = {
            "Accept": "*/*",
            "Origin": INSTAGRAM_BASE_URL,
            "X-IG-App-ID": app_id,
            "X-ASBD-ID": "359341",
            "X-IG-WWW-Claim": "0",
        }

        ruling_started_at = time.monotonic()
        ruling = anonymous_session.client.get(
            RULING_URL,
            params={"content_type": "MEDIA", "target_id": media_id},
            headers=headers,
            allow_redirects=False,
            timeout=timeout,
        )
        interactions.append(self._interaction(
            response=ruling,
            method="GET",
            url=str(ruling.url),
            request_headers=headers,
            duration_seconds=time.monotonic() - ruling_started_at,
            include_response_body=True,
        ))

        ruling_json = self._response_json(ruling)

        if ruling.status_code != 200 or ruling_json.get("status") != "ok":
            return self._retry_or_fail(
                command,
                interactions,
                key,
                allow_session_retry,
                f"Instagram content ruling failed with HTTP {ruling.status_code}.",
            )

        graphql_headers = {
            **headers,
            "Content-Type": "application/x-www-form-urlencoded",
            "X-FB-Friendly-Name": DETAIL_FRIENDLY_NAME,
            "X-Requested-With": "XMLHttpRequest",
            "Referer": f"{INSTAGRAM_BASE_URL}/reel/{shortcode}/",
        }

        if anonymous_session.csrf_token:
            graphql_headers["X-CSRFToken"] = anonymous_session.csrf_token

        if anonymous_session.lsd_token:
            graphql_headers["X-FB-LSD"] = anonymous_session.lsd_token

        graphql_form = {
            "lsd": anonymous_session.lsd_token or "",
            "fb_api_caller_class": "RelayModern",
            "fb_api_req_friendly_name": DETAIL_FRIENDLY_NAME,
            "server_timestamps": "true",
            "variables": json.dumps({"media_id": media_id}, separators=(",", ":")),
            "doc_id": DETAIL_DOC_ID,
        }
        logged_graphql_form = {
            **graphql_form,
            "lsd": "[redacted]" if graphql_form["lsd"] else "",
        }

        graphql_started_at = time.monotonic()
        graphql = anonymous_session.client.post(
            DETAIL_GRAPHQL_URL,
            headers=graphql_headers,
            data=graphql_form,
            allow_redirects=False,
            timeout=timeout,
        )
        interactions.append(self._interaction(
            response=graphql,
            method="POST",
            url=DETAIL_GRAPHQL_URL,
            request_headers=self._safe_graphql_headers(graphql_headers),
            request_body=urlencode(logged_graphql_form),
            duration_seconds=time.monotonic() - graphql_started_at,
            include_response_body=True,
        ))

        graphql_json = self._response_json(graphql)
        media = (
            graphql_json
            .get("data", {})
            .get("xig_polaris_media", {})
            .get("if_not_gated_logged_out")
        )

        if graphql.status_code != 200 or not isinstance(media, dict):
            return self._retry_or_fail(
                command,
                interactions,
                key,
                allow_session_retry,
                f"Instagram detail GraphQL failed with HTTP {graphql.status_code}.",
            )

        return {
            "ok": True,
            "media": media,
            "interactions": interactions,
        }

    def _resolve_profile(
        self,
        command: dict[str, Any],
        interactions: list[dict[str, Any]],
        allow_session_retry: bool,
    ) -> dict[str, Any]:
        username = str(command["username"]).strip().lstrip("@")
        app_id = str(command.get("app_id") or "936619743392459")
        impersonate = str(command.get("impersonate") or "chrome")
        timeout = int(command.get("request_timeout_seconds") or 45)
        session_ttl = int(command.get("session_ttl_seconds") or 300)
        proxy = command.get("proxy")
        key = proxy_key(proxy)

        if not username:
            raise ValueError("Instagram username is required.")

        anonymous_session = self._get_session(
            key=key,
            proxy=proxy,
            impersonate=impersonate,
            timeout=timeout,
            ttl_seconds=session_ttl,
            interactions=interactions,
        )

        headers = {
            "Accept": "*/*",
            "Content-Type": "application/x-www-form-urlencoded",
            "Origin": INSTAGRAM_BASE_URL,
            "Referer": f"{INSTAGRAM_BASE_URL}/{username}/",
            "X-ASBD-ID": "359341",
            "X-FB-Friendly-Name": PROFILE_FRIENDLY_NAME,
            "X-IG-App-ID": app_id,
            "X-IG-WWW-Claim": "0",
            "X-Requested-With": "XMLHttpRequest",
        }

        if anonymous_session.csrf_token:
            headers["X-CSRFToken"] = anonymous_session.csrf_token

        if anonymous_session.lsd_token:
            headers["X-FB-LSD"] = anonymous_session.lsd_token

        graphql_form = {
            "lsd": anonymous_session.lsd_token or "",
            "fb_api_caller_class": "RelayModern",
            "fb_api_req_friendly_name": PROFILE_FRIENDLY_NAME,
            "server_timestamps": "true",
            "variables": json.dumps({"username": username}, separators=(",", ":")),
            "doc_id": PROFILE_DOC_ID,
        }
        logged_graphql_form = {
            **graphql_form,
            "lsd": "[redacted]" if graphql_form["lsd"] else "",
        }

        started_at = time.monotonic()
        response = anonymous_session.client.post(
            DETAIL_GRAPHQL_URL,
            headers=headers,
            data=graphql_form,
            allow_redirects=False,
            timeout=timeout,
        )
        interactions.append(self._interaction(
            response=response,
            method="POST",
            url=DETAIL_GRAPHQL_URL,
            request_headers=self._safe_graphql_headers(headers),
            request_body=urlencode(logged_graphql_form),
            duration_seconds=time.monotonic() - started_at,
            include_response_body=True,
        ))

        profile = (
            self._response_json(response)
            .get("data", {})
            .get("xig_user_by_username")
        )

        if response.status_code != 200 or not isinstance(profile, dict):
            session = self.sessions.pop(key, None)

            if session is not None:
                session.client.close()

            if allow_session_retry:
                return self._resolve_profile(
                    command,
                    interactions,
                    allow_session_retry=False,
                )

            return {
                "ok": False,
                "error": f"Instagram profile GraphQL failed with HTTP {response.status_code}.",
                "interactions": interactions,
            }

        user_id = profile.get("pk")

        if user_id is None or str(user_id) == "":
            return {
                "ok": False,
                "error": "Instagram profile GraphQL returned no profile pk.",
                "interactions": interactions,
            }

        return {
            "ok": True,
            "profile": {
                "id": str(user_id),
                "username": str(profile.get("username") or username),
            },
            "interactions": interactions,
        }

    def _get_session(
        self,
        key: str,
        proxy: dict[str, Any] | None,
        impersonate: str,
        timeout: int,
        ttl_seconds: int,
        interactions: list[dict[str, Any]],
    ) -> AnonymousSession:
        existing = self.sessions.get(key)

        if existing is not None and time.monotonic() - existing.created_at < ttl_seconds:
            return existing

        if existing is not None:
            existing.client.close()

        client = requests.Session(impersonate=impersonate)
        configured_proxy_url = proxy_url(proxy)

        if configured_proxy_url:
            client.proxies = {
                "http": configured_proxy_url,
                "https": configured_proxy_url,
            }

        home_headers = {"Accept": "text/html,application/xhtml+xml"}
        home_started_at = time.monotonic()
        home = client.get(
            f"{INSTAGRAM_BASE_URL}/",
            headers=home_headers,
            allow_redirects=False,
            timeout=timeout,
        )
        interactions.append(self._interaction(
            response=home,
            method="GET",
            url=f"{INSTAGRAM_BASE_URL}/",
            request_headers=home_headers,
            duration_seconds=time.monotonic() - home_started_at,
            include_response_body=False,
        ))

        if home.status_code != 200:
            client.close()
            raise RuntimeError(f"Instagram anonymous session failed with HTTP {home.status_code}.")

        csrf_token = client.cookies.get("csrftoken")
        lsd_match = re.search(r'\["LSD",\[\],\{"token":"([^"]+)"', home.text)

        if lsd_match is None:
            lsd_match = re.search(r'"LSD".*?"token":"([^"]+)"', home.text, re.S)

        anonymous_session = AnonymousSession(
            client=client,
            csrf_token=csrf_token,
            lsd_token=lsd_match.group(1) if lsd_match else None,
            created_at=time.monotonic(),
        )
        self.sessions[key] = anonymous_session

        return anonymous_session

    def _retry_or_fail(
        self,
        command: dict[str, Any],
        interactions: list[dict[str, Any]],
        key: str,
        allow_session_retry: bool,
        error: str,
    ) -> dict[str, Any]:
        session = self.sessions.pop(key, None)

        if session is not None:
            session.client.close()

        if allow_session_retry:
            return self._fetch(command, interactions, allow_session_retry=False)

        return {
            "ok": False,
            "error": error,
            "interactions": interactions,
        }

    @staticmethod
    def _response_json(response: requests.Response) -> dict[str, Any]:
        try:
            decoded = response.json()
        except Exception:
            return {}

        return decoded if isinstance(decoded, dict) else {}

    @staticmethod
    def _safe_graphql_headers(headers: dict[str, str]) -> dict[str, str]:
        return {
            name: value
            for name, value in headers.items()
            if name.lower() not in {"x-csrftoken", "x-fb-lsd"}
        }

    @staticmethod
    def _interaction(
        response: requests.Response,
        method: str,
        url: str,
        request_headers: dict[str, str],
        duration_seconds: float,
        request_body: str | None = None,
        include_response_body: bool = False,
    ) -> dict[str, Any]:
        return {
            "method": method,
            "url": url,
            "request_headers": request_headers,
            "request_body": request_body,
            "status_code": response.status_code,
            "response_body": response.text if include_response_body else None,
            "duration_seconds": duration_seconds,
            "error": None,
        }


def main() -> None:
    client = InstagramReelClient()

    for line in sys.stdin:
        try:
            command = json.loads(line)

            if not isinstance(command, dict):
                raise ValueError("Command must be a JSON object.")

            result = client.handle(command)
        except Exception as exception:
            result = {
                "ok": False,
                "error": f"{type(exception).__name__}: {exception}",
                "interactions": [],
            }

        sys.stdout.write(json.dumps(result, ensure_ascii=False, separators=(",", ":")) + "\n")
        sys.stdout.flush()


if __name__ == "__main__":
    main()
