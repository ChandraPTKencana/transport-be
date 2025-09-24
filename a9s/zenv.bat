@echo off
echo %1
SETLOCAL ENABLEDELAYEDEXPANSION

SET pabrik=%1

IF "%1"=="" (
    xcopy /y .env_xlocal .env
) ELSE (


    IF /I "!pabrik!"=="arp" (
        xcopy /y .env_arp .env
    ) ELSE IF /I "!pabrik!"=="kas" (
        xcopy /y .env_kas .env
    ) ELSE IF /I "!pabrik!"=="kus" (
        xcopy /y .env_kus .env
    ) ELSE IF /I "!pabrik!"=="kap" (
        xcopy /y .env_kap .env
    ) ELSE IF /I "!pabrik!"=="smp" (
        xcopy /y .env_smp .env
    ) ELSE IF /I "!pabrik!"=="kpn" (
        xcopy /y .env_kpn .env
    )
)

