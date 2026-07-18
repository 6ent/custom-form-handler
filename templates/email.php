<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?php echo $email_title; ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <tr>
          <td style="background:#E86401;padding:24px 32px;">
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.5px;">
              <?php echo $email_title; ?>
            </h1>
          </td>
        </tr>

        <tr>
          <td style="padding:32px;">
            <p style="margin:0 0 20px;color:#333;font-size:15px;line-height:1.5;">
              <?php echo $email_intro; ?>
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:28px;">
              <tr>
                <td colspan="2" style="background:#f8f8f8;padding:10px 16px;border-radius:6px 6px 0 0;">
                  <strong style="color:#E86401;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;">Anfrage-Details</strong>
                </td>
              </tr>
              <?php foreach ( $detail_rows as $index => $row ) : ?>
                <tr style="border-bottom:1px solid #eee;<?php echo 1 === $index % 2 ? 'background:#fafafa;' : ''; ?>">
                  <td style="padding:12px 16px;color:#666;font-size:14px;width:45%;vertical-align:top;"><?php echo $row['label']; ?></td>
                  <td style="padding:12px 16px;color:#333;font-size:14px;font-weight:bold;"><?php echo $row['value']; ?></td>
                </tr>
              <?php endforeach; ?>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:28px;">
              <tr>
                <td colspan="2" style="background:#f8f8f8;padding:10px 16px;border-radius:6px 6px 0 0;">
                  <strong style="color:#E86401;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;">Kontaktdaten</strong>
                </td>
              </tr>
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px 16px;color:#666;font-size:14px;width:45%;vertical-align:top;">Name</td>
                <td style="padding:12px 16px;color:#333;font-size:14px;font-weight:bold;"><?php echo $name; ?></td>
              </tr>
              <tr style="border-bottom:1px solid #eee;background:#fafafa;">
                <td style="padding:12px 16px;color:#666;font-size:14px;vertical-align:top;">E-Mail</td>
                <td style="padding:12px 16px;color:#333;font-size:14px;">
                  <a href="mailto:<?php echo $email; ?>" style="color:#E86401;text-decoration:none;"><?php echo $email; ?></a>
                </td>
              </tr>
              <tr>
                <td style="padding:12px 16px;color:#666;font-size:14px;vertical-align:top;">Telefon</td>
                <td style="padding:12px 16px;color:#333;font-size:14px;"><?php echo $phone; ?></td>
              </tr>
            </table>

            <?php if ( ! empty( $tracking_rows ) ) : ?>
              <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:28px;">
                <tr>
                  <td colspan="2" style="background:#f8f8f8;padding:10px 16px;border-radius:6px 6px 0 0;">
                    <strong style="color:#E86401;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;">Tracking</strong>
                  </td>
                </tr>
                <?php foreach ( $tracking_rows as $index => $row ) : ?>
                  <tr style="border-bottom:1px solid #eee;<?php echo 1 === $index % 2 ? 'background:#fafafa;' : ''; ?>">
                    <td style="padding:12px 16px;color:#666;font-size:14px;width:45%;vertical-align:top;"><?php echo $row['label']; ?></td>
                    <td style="padding:12px 16px;color:#333;font-size:14px;word-break:break-word;"><?php echo $row['value']; ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>

            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" style="padding:8px 0 24px;">
                  <a href="mailto:<?php echo $email; ?>?subject=<?php echo $reply_subject; ?>"
                     style="display:inline-block;background:#E86401;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:5px;font-size:15px;font-weight:bold;">
                    Jetzt antworten
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="background:#f8f8f8;padding:16px 32px;border-top:1px solid #eee;">
            <p style="margin:0;color:#999;font-size:12px;line-height:1.5;">
              Diese E-Mail wurde automatisch generiert am <?php echo $submitted; ?> -
              Custom Form Handler v<?php echo CFH_VERSION; ?>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

</body>
</html>
