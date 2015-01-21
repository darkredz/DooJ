package com.doophp.util;

import com.caucho.quercus.env.Env;
import com.caucho.quercus.env.StringValue;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.net.URLEncoder;

/**
 * Created by leng on 12/29/14.
 */
public class Security {

    public static StringValue hmacSha1(Env env, String secretkey, String rurl)
    {
        try{
            Mac mac = Mac.getInstance("HmacSHA1");
            SecretKeySpec secret = new SecretKeySpec(secretkey.getBytes(),"HmacSHA1");
            mac.init(secret);
            byte[] digest = mac.doFinal(rurl.getBytes());
            String signature = new sun.misc.BASE64Encoder().encode(digest);
            signature = URLEncoder.encode(signature, "UTF-8");
            return env.createString(signature);
        }
        catch(Exception err){

        }
        return null;
    }
}
