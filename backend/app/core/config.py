import os
from typing import List, Union
from pydantic import AnyHttpUrl, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    APP_ENV: str = "development"
    APP_DEBUG: bool = True
    APP_SECRET_KEY: str = "cwhub_secret_key_default"

    # JWT Authentication
    JWT_SECRET_KEY: str = "cwhub_jwt_secret_key_default_change_in_production"
    JWT_ALGORITHM: str = "HS256"
    JWT_ACCESS_TOKEN_EXPIRE_MINUTES: int = 1440  # 24 horas

    # Database
    DB_HOST: str = "cpanel.conlineweb.com"
    DB_PORT: int = 3306
    DB_USER: str = "admin_clientes"
    DB_PASS: str = ""
    DB_NAME: str = "admin_clientes"
    DATABASE_URL: str = ""

    # HostingPro DB (opcional)
    DATABASE_URL_HP: str = ""

    # CORS
    BACKEND_HOST: str = "0.0.0.0"
    BACKEND_PORT: int = 8000
    CORS_ORIGINS: List[str] = [
        "http://localhost:5180",
        "http://127.0.0.1:5180",
        "http://localhost:5173",
        "http://127.0.0.1:5173",
        "http://localhost:3000",
        "https://adm.conlineweb.com",
        "https://cliente.conlineweb.com",
    ]

    model_config = SettingsConfigDict(
        env_file=os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(__file__)))), ".env"),
        env_file_encoding="utf-8",
        extra="ignore"
    )

    def get_database_url(self) -> str:
        import urllib.parse
        if self.DATABASE_URL:
            return self.DATABASE_URL
        quoted_user = urllib.parse.quote_plus(self.DB_USER)
        quoted_pass = urllib.parse.quote_plus(self.DB_PASS)
        return (
            f"mysql+pymysql://{quoted_user}:{quoted_pass}@{self.DB_HOST}:{self.DB_PORT}/{self.DB_NAME}?charset=utf8mb4"
        )


settings = Settings()
