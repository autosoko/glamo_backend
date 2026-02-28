<style>
  @import url('https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800;900&display=swap');

  .authNova {
    --ink: #15253e;
    --muted: #60758f;
    --line: #dce6f1;
    --surface: #ffffff;
    --primary: #de6445;
    --primary-2: #c44d31;
    --mint: #1f8a70;
    --mint-soft: #dff6ef;
    --sky-soft: #ebf7ff;
    --peach-soft: #fff2e8;
    background:
      radial-gradient(960px 420px at 8% -8%, #ffe9da 0%, transparent 62%),
      radial-gradient(760px 420px at 94% 6%, #def3ff 0%, transparent 62%),
      linear-gradient(180deg, #fffdfb 0%, #f8fcff 54%, #ffffff 100%);
    padding: 34px 0 42px;
    font-family: 'Raleway', sans-serif;
    color: var(--ink);
  }

  .authNova__wrap {
    display: grid;
    grid-template-columns: 1.05fr .95fr;
    gap: 18px;
    align-items: stretch;
  }

  .authNova__visual {
    border-radius: 24px;
    border: 1px solid #e2ebf5;
    background:
      radial-gradient(220px 120px at 8% 100%, rgba(255, 206, 153, .5) 0%, transparent 90%),
      radial-gradient(220px 140px at 100% 0%, rgba(159, 220, 255, .42) 0%, transparent 88%),
      linear-gradient(150deg, #ffffff 0%, #f5fbff 100%);
    box-shadow: 0 16px 34px rgba(20, 35, 58, .1);
    padding: clamp(20px, 4vw, 34px);
    display: grid;
    gap: 14px;
    align-content: start;
    position: relative;
    overflow: hidden;
  }

  .authNova__visual::after {
    content: '';
    position: absolute;
    width: 170px;
    height: 170px;
    border-radius: 999px;
    background: radial-gradient(circle at 35% 35%, rgba(223, 100, 69, .24) 0%, rgba(223, 100, 69, 0) 72%);
    right: -45px;
    bottom: -60px;
    pointer-events: none;
  }

  .authNova__badge {
    width: fit-content;
    border-radius: 999px;
    padding: 7px 12px;
    border: 1px solid #ffc8ae;
    background: #ffe4d5;
    color: #8a311f;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .authNova__heroTitle {
    font-family: 'Raleway', sans-serif;
    font-size: clamp(28px, 4.2vw, 44px);
    line-height: 1.04;
    letter-spacing: -0.02em;
    margin: 0;
    color: #122742;
    max-width: 12ch;
  }

  .authNova__heroText {
    font-size: 15px;
    line-height: 1.65;
    color: #5f738a;
    margin: 0;
    max-width: 54ch;
  }

  .authNova__checks {
    display: grid;
    gap: 8px;
    margin-top: 4px;
  }

  .authNova__check {
    border: 1px solid #dce8f4;
    background: #fff;
    border-radius: 12px;
    padding: 9px 10px;
    font-size: 13px;
    color: #516882;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .authNova__check::before {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--mint);
    box-shadow: 0 0 0 5px rgba(31, 138, 112, .18);
    flex: 0 0 10px;
  }

  .authNova__miniStats {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 4px;
  }

  .authNova__miniStat {
    border: 1px solid #e1eaf4;
    background: #fff;
    border-radius: 12px;
    padding: 10px 8px;
    text-align: center;
  }

  .authNova__miniStat strong {
    display: block;
    font-family: 'Raleway', sans-serif;
    font-size: 20px;
    color: #1a3b63;
  }

  .authNova__miniStat span {
    display: block;
    margin-top: 2px;
    color: #6a829b;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 700;
  }

  .authNova__card {
    border-radius: 24px;
    border: 1px solid #e0e9f3;
    background: #ffffff;
    box-shadow: 0 16px 34px rgba(20, 35, 58, .11);
    padding: clamp(18px, 3vw, 30px);
  }

  .authNova__title {
    margin: 0;
    font-family: 'Raleway', sans-serif;
    font-size: clamp(26px, 3.2vw, 34px);
    color: #132845;
    line-height: 1.1;
  }

  .authNova__subtitle {
    margin: 7px 0 0;
    color: #60758f;
    font-size: 14px;
    line-height: 1.6;
  }

  .authNova__errors {
    margin-top: 12px;
    border: 1px solid #ffd2d2;
    background: #fff2f2;
    border-radius: 12px;
    padding: 10px 12px;
    color: #a03a3a;
    font-size: 13px;
  }

  .authNova__errors ul {
    margin: 0;
    padding-left: 18px;
  }

  .authNovaTabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin: 16px 0 14px;
    padding: 6px;
    border-radius: 14px;
    border: 1px solid #dce6f2;
    background: #f7fbff;
  }

  .authNovaTab {
    border: 0;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 700;
    color: #4e6884;
    background: transparent;
    cursor: pointer;
    transition: all .2s ease;
  }

  .authNovaTab.is-active {
    background: #fff;
    color: #1d3c62;
    box-shadow: 0 6px 16px rgba(20, 36, 59, .1);
  }

  .authNovaTab__hint {
    display: block;
    font-size: 10px;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-top: 2px;
    opacity: .82;
  }

  .authNovaForm {
    margin-top: 6px;
    display: grid;
    gap: 12px;
  }

  .authNovaLabel {
    display: block;
    margin-bottom: 5px;
    color: #5d7390;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .07em;
    font-weight: 800;
  }

  .authNovaInput,
  .authNovaSelect {
    width: 100%;
    min-height: 44px;
    border-radius: 12px;
    border: 1px solid #d7e3ef;
    background: #fbfdff;
    color: #1e3a5c;
    font: inherit;
    font-size: 14px;
    padding: 10px 12px;
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease;
  }

  .authNovaInput:focus,
  .authNovaSelect:focus {
    border-color: #8eb8df;
    box-shadow: 0 0 0 4px rgba(129, 180, 228, .18);
  }

  .authNovaPhoneRow {
    display: grid;
    grid-template-columns: 165px 1fr;
    gap: 8px;
  }

  .authNovaRole {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .authNovaRoleBtn {
    border: 1px solid #d9e5f1;
    border-radius: 11px;
    background: #f7fbff;
    color: #4f6783;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 10px;
    cursor: pointer;
    transition: all .2s ease;
    text-align: center;
  }

  .authNovaRoleBtn.is-active {
    border-color: #ffcfbb;
    background: #fff2ea;
    color: #a34a33;
  }

  .authNovaBtn {
    border: 0;
    border-radius: 999px;
    min-height: 46px;
    padding: 12px 18px;
    font-size: 14px;
    font-weight: 800;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform .2s ease, box-shadow .2s ease;
  }

  .authNovaBtn:hover { transform: translateY(-1px); }

  .authNovaBtn--primary {
    width: 100%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%);
    color: #fff;
    box-shadow: 0 10px 24px rgba(196, 77, 49, .3);
  }

  .authNovaMeta {
    margin-top: 2px;
    color: #627892;
    font-size: 12px;
    line-height: 1.5;
  }

  .authNovaLinks {
    margin-top: 2px;
    color: #60758f;
    font-size: 13px;
    line-height: 1.55;
  }

  .authNovaLinks a {
    color: #235482;
    text-decoration: none;
    font-weight: 700;
  }

  .authNovaLinks a:hover {
    text-decoration: underline;
  }

  .authNovaErr {
    margin-top: 6px;
    color: #b23d3d;
    font-size: 12px;
    font-weight: 600;
  }

  .authOtp {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
  }

  .authOtp__digit {
    min-height: 52px;
    border-radius: 12px;
    border: 1px solid #d8e4f0;
    background: #fbfdff;
    color: #153359;
    font-family: 'Raleway', sans-serif;
    font-size: 24px;
    text-align: center;
    outline: none;
  }

  .authOtp__digit:focus {
    border-color: #8eb8df;
    box-shadow: 0 0 0 4px rgba(129, 180, 228, .18);
  }

  .authOtp__hint {
    color: #647b95;
    font-size: 12px;
    margin-top: 2px;
  }

  .authNovaInlineHint {
    margin-top: 6px;
    border-radius: 10px;
    border: 1px solid #d4e7f7;
    background: #edf7ff;
    color: #2e5a85;
    padding: 8px 10px;
    font-size: 12px;
    line-height: 1.45;
  }

  @media (max-width: 1050px) {
    .authNova__wrap {
      grid-template-columns: 1fr;
    }

    .authNova__visual {
      order: 2;
    }
  }

  @media (max-width: 680px) {
    .authNova {
      padding-top: 20px;
      padding-bottom: 28px;
    }

    .authNova__miniStats {
      grid-template-columns: 1fr 1fr;
    }

    .authNovaPhoneRow {
      grid-template-columns: 1fr;
    }

    .authOtp {
      gap: 6px;
    }

    .authOtp__digit {
      min-height: 48px;
      font-size: 21px;
    }
  }
</style>
