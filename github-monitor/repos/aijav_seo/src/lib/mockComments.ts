import type { Review } from "@/types/review.types.ts";

// Cache for persisted mock comment by video ID
const mockCommentCache = new Map<number, Review>();

// Realistic NSFW video usernames - actual style from adult sites
const REALISTIC_USERNAMES = [
  "mark1987",
  "thundercock88",
  "alex_lover",
  "mike2000",
  "cumdump_fantasy",
  "chris95",
  "bigdick77",
  "robert84",
  "hornytom",
  "kevin_xxx",
  "ryan1",
  "sexmachine79",
  "brian85",
  "jasonlovescock",
  "scott88",
  "andrew_beast",
  "eric94_",
  "paulxxx",
  "peter89",
  "daniel_sex",
  "michael87",
  "cumshot_king",
  "richard81",
  "joseph_hard",
  "anthony99",
  "frankie77",
  "thomas90",
  "garyhotshot",
  "william88",
  "georgecock",
  "joseph89_",
  "horny_henry",
  "douglas86",
  "julian92",
  "martin_lover",
  "vincent91",
  "roger_sex",
  "philip83",
  "nicolas94",
  "edward79",
  "dave2001",
  "cumslut_fan",
  "mark_hard",
  "sexy_alex",
  "mike_beast",
  "david_xxx",
  "chris_lover",
  "james88",
  "jackoff_bob",
  "tom_hot",
  "kevin69",
  "ryandirty",
  "steven_sex",
  "brian_cock",
  "jason_love",
  "scott_dirty",
  "andrew88",
  "eric_fan",
  "paulhot",
  "peter_beast",
  "daniel88",
];

// Language-grouped mock comments for realistic language matching
const MOCK_COMMENTS_BY_LANGUAGE: Record<string, Omit<Review, "id">[]> = {
  en: [
    {
      video_id: 0,
      rating: 5,
      review:
        "Absolutely amazing! Best video I've ever seen! The production quality is top notch and everything about this is perfect.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Great content, really enjoyed watching this! The chemistry between the performers is incredible and the scene flow is really well done.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Outstanding performance! Highly recommended! This is exactly the kind of content I've been looking for. Everything is executed perfectly.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "Good, but could be better. Some parts were slow. Still enjoyable overall though, the production value is decent enough.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Really enjoyed this! Can't wait for the next one. The performers did an amazing job and the camera work was excellent throughout.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Perfect! Everything about this is excellent. The production quality, the performers, the direction - all top tier stuff right here.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 2,
      review:
        "Not what I expected. Disappointed with the quality. The pacing felt rushed and the production value could have been much better.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Worth watching! Great entertainment value. The content is exactly what you'd want and the quality is really solid overall.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Loved every minute of this! Simply fantastic! The performers are beautiful and talented, the scenes are hot and well-executed. Absolutely perfect.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "Average. It was okay, nothing special really. Had its moments but overall pretty standard content for this type of video.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Exceptional work! Very impressed with the quality. This producer really knows what they're doing. The attention to detail is outstanding.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "This is absolutely superb! Exceeded all my expectations. From the opening scene to the credits, this was pure quality filmmaking.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Amazing production quality! Definitely recommend to friends. The cinematography and editing are professional grade, everything flows perfectly.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "Decent content. Some good moments, some boring ones. The story could have been better paced and there were some unnecessary filler scenes.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Absolutely brilliant! Worth every second of my time! This is the kind of content that sets the standard for everyone else in the industry.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 2,
      review:
        "I had higher hopes. The plot was confusing and hard to follow. The production values seemed rushed and the editing could be smoother.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Very well done! Professional and engaging throughout. The team clearly put a lot of effort into making this and it really shows.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "One of the best I've seen in a long time! The quality is consistently excellent and every scene is well thought out and executed perfectly.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "It's fine, but nothing groundbreaking here. It's enjoyable enough to watch but doesn't really stand out from the typical content in this category.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Thoroughly enjoyed! Looking forward to more content. The release quality and consistency is really impressive, keep up the great work!",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Top-notch quality! This is exactly what I was looking for! No complaints at all, everything is perfect from start to finish.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
  ],
  zh: [
    {
      video_id: 0,
      rating: 5,
      review:
        "太棒了！这是我看过最好的视频！制作质量绝对是顶级的，每一个细节都做得完美无缺。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "非常好！内容很有趣，强烈推荐！演员们的表现令人印象深刻，整个场景的流动也很自然。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "杰出的表现！完全超出了我的期望！这个制作团队真的知道如何做好事情，从开头到结尾都是高质量的。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "还不错，但有些部分可以做得更好。整体的制作质量还不错，但有几个场景的节奏可以改进。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "很喜欢！期待下一期的内容。演员们的表现非常出色，每一个场景都做得很自然。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "完美！这一切都是令人难以置信的！制作质量、演员、镜头工作——一切都是顶级的。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 2,
      review:
        "不符合我的期望，质量令人失望。节奏太快了，有些细节没做好，整体感觉仓促。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "值得一看！有很好的娱乐价值。这正是我想要看的内容，质量也很扎实。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "喜欢每一个细节！绝对是最棒的！演员漂亮有天赋，场景火热执行完美。无可挑剔。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "平均水平。还好，没什么特别的。有些时刻不错，但总体来说比较标准的内容。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "出色的作品！质量令人印象深刻！制作团队真的知道他们在做什么，每个细节都经过精心设计。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "绝对精彩！超出了我的想象！从开场到结束，这都是高质量的电影制作，真的很棒。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "制作得非常棒！肯定要推荐给朋友。摄影和剪辑都很专业，一切都配合得很好。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "不错的内容。有些地方很好，有些地方一般。故事可以更紧凑，有些不必要的场景拖累了节奏。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "极其精彩！每一秒都值得观看！这是我最近看过的最好内容之一，绝对值得推荐。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 2,
      review:
        "有点失望。情节不太吸引人。节奏很快，有些细节没有充分发展，总体感觉有些仓促。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "非常专业！从头到尾都很有趣。制作团队的专业素质很高，每一个画面都经过精心安排。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "近期看过最好的！强力推荐！质量始终保持在一个很高的水平，每个场景都经过精心设计。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "还可以，但没什么特别出彩的地方。这是典型的这个类型的内容，看起来不错但不超出期待。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "很享受观看过程！期待更多内容。发行质量和一致性真的很令人印象深刻，继续加油！",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review: "顶级质量！正是我想要的内容！没有任何抱怨，从头到尾一切都完美。",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
  ],
  ru: [
    {
      video_id: 0,
      rating: 5,
      review:
        "Потрясающе! Это лучшее видео, которое я когда-либо видел! Качество продукции на высшем уровне, каждая деталь идеальна.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Отличный контент, очень рекомендую! Актеры были потрясающие, и вся сцена была хорошо снята.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Блестящее исполнение! Превзошло мои ожидания! Это именно такой контент, который я ищу. Все идеально от начала до конца.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review:
        "Хорошо, но некоторые части можно было сделать лучше. Хороший контент в целом, но есть места для улучшения.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Очень нравится! С нетерпением жду следующего! Исполнители были феноменальны, и все сцены были отлично скоординированы.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Идеально! Все это просто удивительно! Производство, исполнители, режиссура - все на высочайшем уровне.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 2,
      review:
        "Не то, что я ожидал. Разочарован качеством. Темп был спешный, и некоторые сцены чувствовали себя недоразвитыми.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Стоит посмотреть! Отличное развлечение. Это именно то, что вы хотели бы видеть, и качество действительно солидное.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Полюбил каждую минуту! Просто фантастика! Исполнители красивы и талантливы, сцены горячие и хорошо исполнены.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review: "Среднее. Было в порядке, ничего особенного.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review:
        "Выдающаяся работа! Впечатлён качеством! Эта команда производителей действительно знает, что они делают. Внимание к деталям потрясающее.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review:
        "Это просто супер! Превзошёл все ожидания! От начала до конца - это высокое качество кинопроизводства.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review: "Замечательное качество производства! Советую друзьям.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review: "Хороший контент. Есть удачные моменты, есть скучные.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review: "Невероятно! Каждая секунда стоит того!",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 2,
      review: "Ожидал большего. Сюжет был скучноват.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review: "Профессионально сделано! Интересно от начала до конца.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review: "Один из лучших за последнее время!",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 3,
      review: "Нормально, но ничего особенного не заметил.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 4,
      review: "Очень понравилось! Жду с нетерпением новых серий.",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
    {
      video_id: 0,
      rating: 5,
      review: "Высочайшее качество! Именно то, что нужно!",
      created_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      updated_at: new Date(
        Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
      ).toISOString(),
      status: 1,
      parent_id: null,
      user: {
        id: Math.floor(Math.random() * 10000),
        username:
          REALISTIC_USERNAMES[
            Math.floor(Math.random() * REALISTIC_USERNAMES.length)
          ],
      },
      like_count: Math.floor(Math.random() * 500),
      replies: [],
    },
  ],
};

/**
 * Get all mock comments (20) in the specified language
 * @param language - The language code (en, zh, ru)
 * @returns Array of 20 mock review comments
 */
export function getRandomMockComment(language: string = "en"): Review[] {
  const commentsForLanguage =
    MOCK_COMMENTS_BY_LANGUAGE[language] || MOCK_COMMENTS_BY_LANGUAGE.en;

  // Generate 20 unique comments with variations
  return commentsForLanguage.map((mockComment) => ({
    id: Math.floor(Math.random() * 999999),
    ...mockComment,
    created_at: new Date(
      Date.now() - Math.random() * 30 * 24 * 60 * 60 * 1000,
    ).toISOString(),
    like_count: Math.floor(Math.random() * 500),
    user: {
      ...mockComment.user,
      id: Math.floor(Math.random() * 10000),
      username: REALISTIC_USERNAMES[Math.floor(Math.random() * REALISTIC_USERNAMES.length)],
    },
  }));
}

/**
 * Clear the mock comment cache
 */
export function clearMockCommentCache(): void {
  mockCommentCache.clear();
}
